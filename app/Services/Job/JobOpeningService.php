<?php

namespace App\Services\Job;

use App\Models\Job\JobOpenings;

/**
 * Lowongan Kerja tidak pernah dibuat manual — cuma muncul otomatis dari approval Permintaan
 * Tenaga Kerja (`ManPowerReqService::approveManPower()`, is_active=false, opened_at=null).
 * Service ini urus siklus hidupnya di portal: Menunggu Dibuka -> Sedang Dibuka -> Sudah Ditutup.
 */
class JobOpeningService
{
    /** Simpan field tanpa mengubah status buka/tutup — dipakai selagi masih "Menunggu Dibuka"
     * atau saat sekadar mengedit deskripsi/kuota tanpa niat mengubah status. */
    public function saveDraft(JobOpenings $jobOpening, array $data): JobOpenings
    {
        $jobOpening->update($data);

        return $jobOpening->fresh();
    }

    /** Buka lowongan (pertama kali atau buka ulang setelah ditutup) — set opened_at kalau belum
     * pernah, aktifkan is_active supaya tampil ke kandidat di panel /candidate. */
    public function publish(JobOpenings $jobOpening, array $data): JobOpenings
    {
        $data['is_active'] = true;
        $data['opened_at'] = $jobOpening->opened_at ?? now();

        $jobOpening->update($data);

        return $jobOpening->fresh();
    }

    /** Tutup lowongan yang sedang dibuka — tetap tercatat pernah dibuka (opened_at tidak
     * direset), supaya statusnya "Sudah Ditutup", bukan balik ke "Menunggu Dibuka". */
    public function close(JobOpenings $jobOpening): JobOpenings
    {
        $jobOpening->update(['is_active' => false]);

        return $jobOpening->fresh();
    }
}
