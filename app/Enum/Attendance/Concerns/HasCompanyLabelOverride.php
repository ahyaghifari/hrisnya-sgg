<?php

namespace App\Enum\Attendance\Concerns;

/**
 * 2 company yang penamaan izinnya beda sendiri - RSU Syifa Medika Banjarbaru & RSU Syifa Medika
 * Barabai (instruksi user 2026-09-30). ID company SAMA di production & development (dikonfirmasi
 * user), aman di-hardcode di sini - bukan lewat tabel Setting karena cuma 2 company tetap, bukan
 * konfigurasi yang perlu diubah-ubah admin dari UI.
 *
 * Label yang berubah HANYA teks tampilan/tersimpan (permission_type) - `category` (dipakai
 * payroll & alur approval) tetap ditentukan dari identitas enum case, TIDAK PERNAH dari teks
 * label, jadi override ini aman terhadap perhitungan payroll (lihat
 * AttendaceReqService::storePermissionRequest() - category & label diresolusi terpisah).
 */
trait HasCompanyLabelOverride
{
    private const OVERRIDE_COMPANY_IDS = [
        '01k8271xa4nq8cezg3dq9vfr14', // RSU Syifa Medika Banjarbaru
        '01k8271xefsez6a80tknntt1pj', // RSU Syifa Medika Barabai
    ];

    public static function usesLabelOverride(?string $companyId): bool
    {
        return $companyId !== null && in_array($companyId, self::OVERRIDE_COMPANY_IDS, true);
    }
}
