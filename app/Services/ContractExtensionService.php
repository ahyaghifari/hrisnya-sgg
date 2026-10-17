<?php

namespace App\Services;

use App\Enum\ContractDecisionEnum;
use App\Enum\EmployeeTypeCategory;
use App\Models\ContractSubmission;
use App\Models\SikartapReviewer;
use App\Models\Setting;
use App\Models\User;
use App\Models\Employee\EmployeeContract;
use App\Notifications\ContractReviewRequested;
use App\Notifications\ContractReviewed;
use App\Notifications\ContractRevisionRequested;
use App\Services\User\UserTransitionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alur perpanjangan kontrak (self-service). Karyawan kategori Kontrak mengajukan + unggah
 * dokumen (daftar dinamis per company), lalu penilai (resolve dari `sikartap_reviewers` context
 * `contract`, lihat SikartapReviewerResolver) memutuskan `perpanjang` atau `tidak_perpanjang`.
 * **Tidak lewat Approval Engine** — 1 penilai memutuskan.
 *
 * Penilai TIDAK menentukan durasi — itu tindak lanjut HR (followUpDuration). `perpanjang` →
 * status `dinilai` (nunggu HR isi durasi) → HR menetapkan durasi → bikin 1 baris
 * `user_contracts` baru (start = hari setelah kontrak lama berakhir / hari ini, end = start +
 * durasi lewat UserTransitionService::resolveContractEndDate yang enforce MAX_KONTRAK_TAHUN +
 * USIA_PENSIUN). Kontrak lama otomatis jadi riwayat.
 *
 * Status: draft → diajukan → dinilai → selesai. (ditolak = tidak_perpanjang; dibatalkan = pengaju batal.)
 */
class ContractExtensionService
{
    public const DOCUMENTS_SETTING_KEY = 'contract_extension_documents';

    public function __construct(
        protected SikartapReviewerResolver $reviewerResolver,
        protected UserTransitionService $transitionService,
    ) {}

    /**
     * Daftar dokumen yang wajib/opsional diunggah pengaju, per company. Urutan array = urutan slot.
     *
     * @return array<int, array{label: string, required: bool}>
     */
    public function requiredDocuments(?string $companyId): array
    {
        $raw = Setting::resolve(self::DOCUMENTS_SETTING_KEY, $companyId, default: null);
        $list = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($list) || $list === []) {
            return [
                ['label' => 'Kontrak Terakhir', 'required' => true],
                ['label' => 'Surat Rekomendasi Atasan', 'required' => true],
            ];
        }

        return collect($list)
            ->map(fn ($row) => [
                'label' => (string) (is_array($row) ? ($row['label'] ?? '') : $row),
                'required' => is_array($row) ? (bool) ($row['required'] ?? false) : true,
            ])
            ->filter(fn ($row) => $row['label'] !== '')
            ->values()
            ->all();
    }

    public function saveRequiredDocuments(?string $companyId, array $list): void
    {
        Setting::updateOrCreate(
            ['key' => self::DOCUMENTS_SETTING_KEY, 'company_id' => $companyId],
            [
                'module' => 'kepegawaian',
                'type' => 'json',
                'label' => 'Dokumen Perpanjangan Kontrak',
                'value' => json_encode(array_values($list)),
            ],
        );
    }

    /** Penilai untuk 1 pengajuan (resolve dari jabatan → departemen). Bisa kosong. */
    public function reviewersFor(ContractSubmission $submission): \Illuminate\Support\Collection
    {
        return $submission->user
            ? $this->reviewerResolver->reviewersFor(SikartapReviewer::CONTEXT_STATUS_KARYAWAN, $submission->user)
            : collect();
    }

    public function canReview(ContractSubmission $submission, User $actor): bool
    {
        return $submission->user
            && $this->reviewerResolver->canReview(SikartapReviewer::CONTEXT_STATUS_KARYAWAN, $submission->user, $actor);
    }

    /** Kirim pengajuan (dari status draft) → cek dokumen wajib, resolve penilai, notifikasi. */
    public function submit(ContractSubmission $submission, User $actor): void
    {
        if ($submission->status !== 'draft') {
            throw new \RuntimeException('Pengajuan ini sudah dikirim.');
        }

        $uploadedLabels = $submission->documents()->pluck('label')->all();

        foreach ($this->requiredDocuments($submission->company_id) as $doc) {
            if ($doc['required'] && ! in_array($doc['label'], $uploadedLabels, true)) {
                throw new \RuntimeException("Dokumen wajib \"{$doc['label']}\" belum diunggah.");
            }
        }

        $reviewers = $this->reviewersFor($submission);

        if ($reviewers->isEmpty()) {
            throw new \RuntimeException('Jabatan penilai perpanjangan kontrak untuk departemen Anda kosong (belum ada pemegang). Hubungi HR.');
        }

        DB::transaction(function () use ($submission, $reviewers) {
            $submission->update([
                'status' => 'diajukan',
                'submitted_at' => now(),
                'decision_note' => null,
                'decided_by' => null,
                'decided_at' => null,
            ]);

            foreach ($reviewers as $reviewer) {
                $reviewer->notify(new ContractReviewRequested($submission));
            }
        });
    }

    /** Penilai mengembalikan pengajuan ke karyawan untuk perbaikan dokumen (status balik draft). */
    public function requestRevision(ContractSubmission $submission, string $actorId, string $note): void
    {
        if ($submission->status !== 'diajukan') {
            throw new \RuntimeException('Pengajuan ini tidak sedang menunggu penilaian.');
        }

        if (trim($note) === '') {
            throw new \RuntimeException('Catatan revisi wajib diisi.');
        }

        DB::transaction(function () use ($submission, $actorId, $note) {
            $submission->update([
                'status' => 'draft',
                'decision_note' => $note,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

            $submission->user?->notify(new ContractRevisionRequested($submission));
        });
    }

    /**
     * Penilai memutuskan (TANPA durasi). `perpanjang` → status `dinilai` (nunggu HR isi durasi
     * lewat followUpDuration). `tidak_perpanjang` → status `ditolak`, tak ada efek lain (kontrak
     * habis alami).
     */
    public function review(
        ContractSubmission $submission,
        string $actorId,
        string $decision,
        string $note,
    ): void {
        $decisionEnum = ContractDecisionEnum::from($decision);

        if (trim($note) === '') {
            throw new \RuntimeException('Catatan keputusan wajib diisi.');
        }

        if ($submission->status !== 'diajukan') {
            throw new \RuntimeException('Pengajuan ini tidak sedang menunggu penilaian.');
        }

        $isPerpanjang = $decisionEnum === ContractDecisionEnum::PERPANJANG;

        DB::transaction(function () use ($submission, $actorId, $note, $decisionEnum, $isPerpanjang) {
            $submission->update([
                'status' => $isPerpanjang ? 'dinilai' : 'ditolak',
                'decision' => $decisionEnum->value,
                'decision_note' => $note,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

            $submission->user?->notify(new ContractReviewed($submission));
        });
    }

    /**
     * Tindak lanjut HR untuk keputusan `perpanjang`: HR menetapkan durasi (bulan), lalu bikin
     * baris `user_contracts` baru menyambung kontrak berjalan. anchor = tanggal berakhir kontrak
     * lama (atau hari ini kalau sudah lewat / tak ada). Status submission jadi `selesai`.
     */
    public function followUpDuration(ContractSubmission $submission, int $durasiBulan, string $actorId): void
    {
        if ($submission->status !== 'dinilai' || $submission->decision !== ContractDecisionEnum::PERPANJANG->value) {
            throw new \RuntimeException('Pengajuan ini bukan menunggu penetapan durasi perpanjangan.');
        }

        if ($durasiBulan < 1) {
            throw new \RuntimeException('Durasi perpanjangan (bulan) wajib diisi.');
        }

        $user = $submission->user;
        $current = $user?->employee?->currentContract ?? $submission->employeeContract;

        if (! $user || ! $current) {
            throw new \RuntimeException('Kontrak berjalan karyawan tidak ditemukan.');
        }

        if ($user->profile?->employeeType?->category !== EmployeeTypeCategory::KONTRAK) {
            throw new \RuntimeException('Karyawan bukan berstatus Kontrak.');
        }

        $anchor = $current->end_date
            ? Carbon::parse($current->end_date)->max(Carbon::today())
            : Carbon::today();

        // Lempar kalau lewat MAX_KONTRAK_TAHUN / USIA_PENSIUN (guard di dalam).
        $end = $this->transitionService->resolveContractEndDate(
            $user,
            $anchor,
            $submission->company_id,
            $durasiBulan,
        );

        DB::transaction(function () use ($submission, $user, $current, $anchor, $end, $durasiBulan) {
            $newContract = EmployeeContract::create([
                'employee_id' => $user->employee->id,
                'company_id' => $submission->company_id,
                'type' => EmployeeContract::TYPE_KONTRAK,
                'employee_type_id' => $current->employee_type_id,
                'start_date' => $anchor->copy()->toDateString(),
                'end_date' => $end->toDateString(),
                'previous_contract_id' => $current->id,
            ]);

            $submission->update([
                'status' => 'selesai',
                'decision_durasi_bulan' => $durasiBulan,
                'new_employee_contract_id' => $newContract->id,
            ]);

            $submission->user?->notify(new ContractReviewed($submission));
        });
    }

    /** Batalkan pengajuan (oleh pengaju sendiri) selama belum dinilai. */
    public function cancel(ContractSubmission $submission, string $actorId): void
    {
        if (in_array($submission->status, ['dinilai', 'selesai', 'ditolak', 'dibatalkan'], true)) {
            throw new \RuntimeException('Pengajuan ini sudah tidak bisa dibatalkan.');
        }

        $submission->update(['status' => 'dibatalkan']);
    }
}
