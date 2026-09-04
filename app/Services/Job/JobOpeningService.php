<?php

namespace App\Services\Job;

use App\Models\Job\JobOpenings;
use App\Services\Rekrutmen\RecruitmentSync;

/**
 * Lowongan Kerja tidak pernah dibuat manual — cuma muncul otomatis dari approval Permintaan
 * Tenaga Kerja (`ManPowerReqService::approveManPower()`, is_active=false, opened_at=null).
 * Service ini urus siklus hidupnya di portal: Menunggu Dibuka -> Sedang Dibuka -> Sudah Ditutup.
 *
 * Tiap perubahan (simpan/publish/tutup) ikut di-upsert ke DB `recruitment` (shared DB) lewat
 * RecruitmentSync::sync() — lihat planning/2026-08-09-integrasi-job-opening-ke-rekrutmen.md.
 * Sync ini non-fatal (gagal tidak menggagalkan aksi di sini, lihat docblock service-nya).
 */
class JobOpeningService
{
    public function __construct(
        protected RecruitmentSync $rekrutmenSync,
    ) {}

    /** Simpan field tanpa mengubah status buka/tutup — dipakai selagi masih "Menunggu Dibuka"
     * atau saat sekadar mengedit deskripsi/kuota tanpa niat mengubah status. */
    public function saveDraft(JobOpenings $jobOpening, array $data): JobOpenings
    {
        $jobOpening->update($data);
        $jobOpening = $jobOpening->fresh();

        $this->rekrutmenSync->sync($jobOpening);

        return $jobOpening;
    }

    /** Buka lowongan (pertama kali atau buka ulang setelah ditutup) — set opened_at kalau belum
     * pernah, aktifkan is_active supaya tampil di sistem Rekrutmen. */
    public function publish(JobOpenings $jobOpening, array $data): JobOpenings
    {
        $data['is_active'] = true;
        $data['opened_at'] = $jobOpening->opened_at ?? now();

        $jobOpening->update($data);
        $jobOpening = $jobOpening->fresh();

        $this->rekrutmenSync->sync($jobOpening);

        return $jobOpening;
    }

    /** Tutup lowongan yang sedang dibuka — tetap tercatat pernah dibuka (opened_at tidak
     * direset), supaya statusnya "Sudah Ditutup", bukan balik ke "Menunggu Dibuka". */
    public function close(JobOpenings $jobOpening): JobOpenings
    {
        $jobOpening->update(['is_active' => false]);
        $jobOpening = $jobOpening->fresh();

        $this->rekrutmenSync->sync($jobOpening);

        return $jobOpening;
    }
}
