<?php

namespace App\Services;

use App\Enum\OrientationDecisionEnum;
use App\Models\OrientationSubmission;
use App\Models\SikartapReviewer;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrientationReviewRequested;
use App\Notifications\OrientationReviewed;
use App\Notifications\OrientationRevisionRequested;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alur penilaian orientasi (self-service). Karyawan orientasi mengajukan + upload bukti +
 * usul tanggal/jam presentasi kegiatan → penilai (resolve dari `sikartap_reviewers` context
 * `orientation`, lihat SikartapReviewerResolver) mengisi skor per indikator per bulan +
 * memilih keputusan (lanjut_kontrak / tidak_dilanjutkan). Paralel: jadwal presentasi kegiatan
 * butuh 2 persetujuan terpisah — HR/pengelola orientasi (confirmPresentation) + pemegang jabatan
 * pendukung (approvePresentationBySupport, Setting orientation_presentation_approver_job_position_id).
 * Keduanya wajib sebelum HR menutup pengajuan. submit() sendiri SUDAH menolak dari awal kalau
 * jabatan pendukungnya belum diatur — gagal cepat, bukan nyangkut nanti di followUpContract/
 * followUpNotContinued.
 *
 * **Tidak lewat Approval Engine** (dilepas 2026-09-06) — 1 penilai memutuskan, bukan routing.
 * Status: draft → diajukan → selesai. (dibatalkan = pengaju batalkan sebelum dinilai.)
 *
 * Indikator penilaian = Setting `orientation_criteria` per company (label saran, di-capture
 * jadi baris `orientation_submission_assessments` saat dinilai — snapshot).
 */
class OrientationService
{
    public const CRITERIA_SETTING_KEY = 'orientation_criteria';

    /** Setting (per company): job_position_id pemegang persetujuan jadwal presentasi (selain HR). */
    public const PRESENTATION_APPROVER_SETTING_KEY = 'orientation_presentation_approver_job_position_id';

    public function __construct(
        protected SikartapReviewerResolver $reviewerResolver,
    ) {}

    /**
     * Daftar indikator penilaian untuk 1 company. Urutan array = urutan tampil. Flat — cuma
     * nama indikator (skala skor fixed 1–5, tidak ada "aspek"/grouping lagi).
     *
     * @return array<int, array{name: string}>
     */
    public function criteria(?string $companyId): array
    {
        $raw = Setting::resolve(self::CRITERIA_SETTING_KEY, $companyId, default: null);

        $list = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($list)) {
            return [];
        }

        return collect($list)
            ->map(fn ($row) => ['name' => (string) (is_array($row) ? ($row['name'] ?? '') : $row)])
            ->filter(fn ($row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    /** Simpan daftar indikator untuk 1 company (dipakai halaman Indikator Orientasi). */
    public function saveCriteria(?string $companyId, array $list): void
    {
        Setting::updateOrCreate(
            ['key' => self::CRITERIA_SETTING_KEY, 'company_id' => $companyId],
            [
                'module' => 'orientation',
                'type' => 'json',
                'label' => 'Indikator Penilaian Orientasi',
                'value' => json_encode(array_values($list)),
            ],
        );
    }

    /** Penilai untuk 1 pengajuan (resolve dari jabatan → departemen). Bisa kosong. */
    public function reviewersFor(OrientationSubmission $submission): \Illuminate\Support\Collection
    {
        return $submission->user
            ? $this->reviewerResolver->reviewersFor(SikartapReviewer::CONTEXT_STATUS_KARYAWAN, $submission->user)
            : collect();
    }

    public function canReview(OrientationSubmission $submission, User $actor): bool
    {
        return $submission->user
            && $this->reviewerResolver->canReview(SikartapReviewer::CONTEXT_STATUS_KARYAWAN, $submission->user, $actor);
    }

    /** Kirim pengajuan (dari status draft) → resolve penilai + notifikasi. */
    public function submit(OrientationSubmission $submission, User $actor): void
    {
        if ($submission->status !== 'draft') {
            throw new \RuntimeException('Pengajuan ini sudah dikirim.');
        }

        if (empty($submission->attachments)) {
            throw new \RuntimeException('Unggah minimal satu bukti kegiatan orientasi dulu.');
        }

        if (! $submission->presentation_proposed_at) {
            throw new \RuntimeException('Usulkan tanggal & jam presentasi kegiatan orientasi dulu.');
        }

        // Gagal cepat di sini, bukan nyangkut belakangan — tanpa ini, followUpContract/
        // followUpNotContinued permanen terblokir (presentationFullyApproved tidak pernah true,
        // tidak ada pemegang jabatan pendukung yang bisa approve) sampai HR sadar & atur settingnya.
        $this->assertPresentationApproverConfigured($submission);

        $reviewers = $this->reviewersFor($submission);

        if ($reviewers->isEmpty()) {
            throw new \RuntimeException('Jabatan penilai untuk departemen Anda kosong (belum ada pemegang). Hubungi HR.');
        }

        DB::transaction(function () use ($submission, $reviewers) {
            $submission->update([
                'status' => 'diajukan',
                'submitted_at' => now(),
                // Bersihkan jejak permintaan revisi sebelumnya (kalau ini re-submit).
                'decision_note' => null,
                'decided_by' => null,
                'decided_at' => null,
            ]);

            foreach ($reviewers as $reviewer) {
                $reviewer->notify(new OrientationReviewRequested($submission));
            }
        });
    }

    /**
     * Penilai mengembalikan pengajuan ke karyawan untuk perbaikan bukti (status balik `draft`).
     * Alasan disimpan di `decision_note`, dibersihkan otomatis saat karyawan submit lagi.
     */
    public function requestRevision(OrientationSubmission $submission, string $actorId, string $note): void
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

            $submission->user?->notify(new OrientationRevisionRequested($submission));
        });
    }

    /**
     * Penilai (atasan): simpan skor per indikator per bulan + keputusan (lanjut_kontrak /
     * tidak_dilanjutkan). Penilai TIDAK mengisi detail tindak lanjut — itu langkah HR
     * (followUpContract / followUpNotContinued). Status jadi `dinilai` (nunggu HR).
     *
     * @param array<int, array{label:string, comment:?string, sort_order?:int, monthly:array<int, array{period_month:string, score:?float, comment:?string}>}> $rows
     */
    public function review(OrientationSubmission $submission, string $actorId, array $rows, string $decision, string $note): void
    {
        $decisionEnum = OrientationDecisionEnum::from($decision);

        if (trim($note) === '') {
            throw new \RuntimeException('Catatan keputusan wajib diisi.');
        }

        $hasScore = collect($rows)
            ->flatMap(fn ($r) => $r['monthly'] ?? [])
            ->contains(fn ($c) => isset($c['score']) && $c['score'] !== '' && $c['score'] !== null);

        if (! $hasScore) {
            throw new \RuntimeException('Isi minimal satu skor bulan sebelum menyimpan keputusan.');
        }

        DB::transaction(function () use ($submission, $actorId, $rows, $decisionEnum, $note) {
            // Ganti total: hapus indikator+skor lama (cascade), buat ulang dari input.
            $submission->assessments()->delete();

            foreach (array_values($rows) as $i => $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $assessment = $submission->assessments()->create([
                    'label' => $label,
                    'comment' => $row['comment'] ?? null,
                    'sort_order' => $row['sort_order'] ?? ($i + 1),
                ]);

                foreach ($row['monthly'] ?? [] as $cell) {
                    if (empty($cell['period_month'])) {
                        continue;
                    }

                    $assessment->scores()->create([
                        'period_month' => Carbon::parse($cell['period_month'])->startOfMonth()->toDateString(),
                        'score' => isset($cell['score']) && $cell['score'] !== '' ? (float) $cell['score'] : null,
                        'comment' => $cell['comment'] ?? null,
                    ]);
                }
            }

            $submission->update([
                'status' => 'dinilai',
                'decision' => $decisionEnum->value,
                'decision_note' => $note,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

            $submission->user?->notify(new OrientationReviewed($submission));
        });
    }

    /** job_position_id pemegang persetujuan jadwal presentasi (jabatan pendukung) untuk 1 company. */
    public function presentationApproverJobPositionId(?string $companyId): ?string
    {
        return $companyId
            ? Setting::resolve(self::PRESENTATION_APPROVER_SETTING_KEY, $companyId, default: null)
            : null;
    }

    /** Pemegang aktif jabatan pendukung untuk company pengajuan ini. */
    public function presentationApproverHolders(OrientationSubmission $submission): \Illuminate\Support\Collection
    {
        $jobPositionId = $this->presentationApproverJobPositionId($submission->company_id);

        if (! $jobPositionId) {
            return collect();
        }

        return \App\Models\User\UserJobPositions::query()
            ->where('job_position_id', $jobPositionId)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function canApprovePresentation(OrientationSubmission $submission, User $actor): bool
    {
        return $this->presentationApproverHolders($submission)->contains('id', $actor->id);
    }

    private function assertPresentationApproverConfigured(OrientationSubmission $submission): void
    {
        if (! $this->presentationApproverJobPositionId($submission->company_id)) {
            throw new \RuntimeException('Jabatan pendukung persetujuan jadwal presentasi belum diatur untuk perusahaan ini (Sikartap → Pengaturan). Hubungi admin.');
        }
    }

    /**
     * Persetujuan HR atas jadwal presentasi kegiatan (tanggal + jam — boleh sama/beda dari usulan
     * karyawan). Ini menetapkan/mengunci slot (`presentation_confirmed_at`) sekaligus jadi
     * persetujuan HR. Kalau slot-nya BERUBAH dari yang sebelumnya, persetujuan jabatan pendukung
     * yang sudah ada otomatis dibatalkan (mereka menyetujui slot lama).
     */
    public function confirmPresentation(OrientationSubmission $submission, string $datetime, ?string $note, string $actorId): void
    {
        if (! in_array($submission->status, ['diajukan', 'dinilai'], true)) {
            throw new \RuntimeException('Jadwal presentasi hanya bisa ditetapkan setelah pengajuan dikirim dan sebelum selesai.');
        }

        $this->assertPresentationApproverConfigured($submission);

        $newAt = Carbon::parse($datetime);
        $slotChanged = $submission->presentation_confirmed_at
            && ! $newAt->equalTo($submission->presentation_confirmed_at);

        $submission->update([
            'presentation_confirmed_at' => $newAt,
            'presentation_note' => trim((string) $note) !== '' ? $note : null,
            'presentation_hr_at' => now(),
            'presentation_hr_by' => $actorId,
            // slot berubah → batalkan persetujuan jabatan pendukung atas slot lama.
            'presentation_support_at' => $slotChanged ? null : $submission->presentation_support_at,
            'presentation_support_by' => $slotChanged ? null : $submission->presentation_support_by,
        ]);

        $this->notifyPresentationProgress($submission->fresh());
    }

    /**
     * Persetujuan jabatan pendukung atas jadwal presentasi. Kalau HR belum mengunci slot,
     * pemegang jabatan pendukung mengunci usulan karyawan sebagai slot (urutan bebas).
     */
    public function approvePresentationBySupport(OrientationSubmission $submission, User $actor): void
    {
        if (! in_array($submission->status, ['diajukan', 'dinilai'], true)) {
            throw new \RuntimeException('Persetujuan hanya bisa setelah pengajuan dikirim dan sebelum selesai.');
        }

        if (! $this->canApprovePresentation($submission, $actor)) {
            throw new \RuntimeException('Anda bukan pemegang jabatan pendukung persetujuan jadwal presentasi.');
        }

        $submission->update([
            'presentation_confirmed_at' => $submission->presentation_confirmed_at ?? $submission->presentation_proposed_at,
            'presentation_support_at' => now(),
            'presentation_support_by' => $actor->id,
        ]);

        $this->notifyPresentationProgress($submission->fresh());
    }

    /** Notifikasi: minta jabatan pendukung approve (kalau belum), & kabari karyawan kalau sudah lengkap. */
    private function notifyPresentationProgress(OrientationSubmission $submission): void
    {
        if ($submission->presentationFullyApproved()) {
            $submission->user?->notify(new \App\Notifications\OrientationPresentationScheduled($submission));

            return;
        }

        if ($submission->presentation_hr_at && ! $submission->presentation_support_at) {
            foreach ($this->presentationApproverHolders($submission) as $holder) {
                $holder->notify(new \App\Notifications\OrientationPresentationApprovalRequested($submission));
            }
        }
    }

    /**
     * Tindak lanjut HR untuk keputusan TIDAK_DILANJUTKAN: HR mencatat tanggal efektif
     * berakhirnya hubungan kerja. Tidak ada periode/kontrak baru — offboarding karyawan
     * ditangani terpisah di luar modul ini. Status submission jadi `selesai`.
     */
    public function followUpNotContinued(OrientationSubmission $submission, string $effectiveEndDate, string $actorId): void
    {
        if ($submission->status !== 'dinilai' || $submission->decision !== OrientationDecisionEnum::TIDAK_DILANJUTKAN->value) {
            throw new \RuntimeException('Pengajuan ini bukan menunggu tindak lanjut "tidak dilanjutkan".');
        }

        if (! $submission->presentationFullyApproved()) {
            throw new \RuntimeException('Jadwal presentasi kegiatan belum disetujui HR & jabatan pendukung.');
        }

        $submission->update([
            'status' => 'selesai',
            'effective_end_date' => Carbon::parse($effectiveEndDate)->toDateString(),
        ]);
    }

    /** Tipe karyawan kategori Kontrak untuk 1 company (diasumsikan cuma 1 — lihat followUpContract). */
    public function kontrakEmployeeType(?string $companyId): ?\App\Models\Master\EmployeeTypes
    {
        return \App\Models\Master\EmployeeTypes::query()
            ->where('company_id', $companyId)
            ->where('category', \App\Enum\EmployeeTypeCategory::KONTRAK->value)
            ->orderBy('name')
            ->first();
    }

    /**
     * Tindak lanjut HR untuk keputusan LANJUT_KONTRAK: langsung angkat karyawan orientasi jadi
     * Kontrak di sini (tanpa lewat Perubahan Status Karyawan) — flip `employee_type_id` di
     * profil (+ kelas minimal dari pangkat) & catat 1 baris `user_contracts`. Status `selesai`.
     *
     * Tipe karyawan tujuan TIDAK dipilih HR — otomatis tipe kategori Kontrak milik company ini
     * (kategori sudah baku lewat EmployeeTypeCategory::KONTRAK; per company hanya ada satu).
     */
    public function followUpContract(
        OrientationSubmission $submission,
        string $contractStartDate,
        string $contractEndDate,
        string $actorId,
    ): void {
        if ($submission->status !== 'dinilai' || $submission->decision !== OrientationDecisionEnum::LANJUT_KONTRAK->value) {
            throw new \RuntimeException('Pengajuan ini bukan menunggu tindak lanjut kontrak.');
        }

        if (! $submission->presentationFullyApproved()) {
            throw new \RuntimeException('Jadwal presentasi kegiatan belum disetujui HR & jabatan pendukung.');
        }

        $type = $this->kontrakEmployeeType($submission->company_id);

        if (! $type) {
            throw new \RuntimeException('Belum ada tipe karyawan kategori Kontrak untuk perusahaan ini. Buat dulu di master Tipe Karyawan.');
        }

        if (Carbon::parse($contractEndDate)->lte(Carbon::parse($contractStartDate))) {
            throw new \RuntimeException('Tanggal akhir kontrak harus setelah tanggal mulai.');
        }

        DB::transaction(function () use ($submission, $type, $contractStartDate, $contractEndDate) {
            $profile = $submission->user?->profile;

            $updates = ['employee_type_id' => $type->id];
            if ($startKelasId = $profile?->pangkat?->start_kelas_id) {
                $updates['kelas_id'] = $startKelasId;
            }
            $profile?->update($updates);

            \App\Models\User\UserContract::create([
                'user_id' => $submission->user_id,
                'company_id' => $submission->company_id,
                'employee_type_id' => $type->id,
                'contract_start_date' => Carbon::parse($contractStartDate)->toDateString(),
                'contract_end_date' => Carbon::parse($contractEndDate)->toDateString(),
            ]);

            $submission->update(['status' => 'selesai']);
        });
    }

    /** Batalkan pengajuan (oleh pengaju sendiri) selama belum dinilai. */
    public function cancel(OrientationSubmission $submission, string $actorId): void
    {
        if (in_array($submission->status, ['selesai', 'dibatalkan'], true)) {
            throw new \RuntimeException('Pengajuan ini sudah tidak bisa dibatalkan.');
        }

        $submission->update(['status' => 'dibatalkan']);
    }
}
