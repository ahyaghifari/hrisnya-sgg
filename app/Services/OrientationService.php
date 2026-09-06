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
 * Alur penilaian orientasi (self-service). Karyawan orientasi mengajukan + upload bukti →
 * penilai (resolve dari `sikartap_reviewers` context `orientation`, lihat
 * SikartapReviewerResolver) mengisi skor per indikator per bulan + memilih keputusan
 * (lulus/perpanjang/tidak_lulus).
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
            ? $this->reviewerResolver->reviewersFor(SikartapReviewer::CONTEXT_ORIENTATION, $submission->user)
            : collect();
    }

    public function canReview(OrientationSubmission $submission, User $actor): bool
    {
        return $submission->user
            && $this->reviewerResolver->canReview(SikartapReviewer::CONTEXT_ORIENTATION, $submission->user, $actor);
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
     * perpanjang). Penilai TIDAK mengisi detail kontrak/perpanjangan — itu tindak lanjut HR
     * (followUpExtend / followUpContract). Status jadi `dinilai` (nunggu HR).
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

    /**
     * Tindak lanjut HR untuk keputusan PERPANJANG: bikin periode orientasi baru
     * (user_orientations, source=extension) — HR isi durasi (bulan) + tanggal mulai. Status
     * submission jadi `selesai`. Karyawan lalu bisa mengajukan penilaian lagi untuk periode baru.
     */
    public function followUpExtend(OrientationSubmission $submission, string $startDate, int $months, string $actorId): void
    {
        if ($submission->status !== 'dinilai' || $submission->decision !== OrientationDecisionEnum::PERPANJANG->value) {
            throw new \RuntimeException('Pengajuan ini bukan menunggu tindak lanjut perpanjangan.');
        }

        if ($months < 1) {
            throw new \RuntimeException('Lama perpanjangan (bulan) wajib diisi.');
        }

        DB::transaction(function () use ($submission, $startDate, $months) {
            $start = Carbon::parse($startDate);
            $end = $start->copy()->addMonths($months)->subDay();

            \App\Models\User\UserOrientation::create([
                'user_id' => $submission->user_id,
                'company_id' => $submission->company_id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'duration_months' => $months,
                'source' => 'extension',
                'previous_orientation_id' => $submission->user_orientation_id,
            ]);

            $submission->update([
                'status' => 'selesai',
                'extended_until' => $end->toDateString(),
            ]);
        });
    }

    /**
     * Tindak lanjut HR untuk keputusan LANJUT_KONTRAK: langsung angkat karyawan orientasi jadi
     * Kontrak di sini (tanpa lewat Perubahan Status Karyawan) — flip `employee_type_id` di
     * profil (+ kelas minimal dari pangkat) & catat 1 baris `user_contracts`. Status `selesai`.
     *
     * Modul kontrak lengkap (perpanjangan, surat kontrak, dst.) dikerjakan terpisah nanti.
     */
    public function followUpContract(
        OrientationSubmission $submission,
        string $employeeTypeId,
        string $contractStartDate,
        string $contractEndDate,
        string $actorId,
    ): void {
        if ($submission->status !== 'dinilai' || $submission->decision !== OrientationDecisionEnum::LANJUT_KONTRAK->value) {
            throw new \RuntimeException('Pengajuan ini bukan menunggu tindak lanjut kontrak.');
        }

        $type = \App\Models\Master\EmployeeTypes::find($employeeTypeId);

        if (! $type || $type->company_id !== $submission->company_id || $type->category !== \App\Enum\EmployeeTypeCategory::KONTRAK) {
            throw new \RuntimeException('Tipe karyawan tujuan harus kategori Kontrak dari perusahaan yang sama.');
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
