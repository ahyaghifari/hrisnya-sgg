<?php

namespace App\Enum\JobPosition;

/**
 * Company yang pengajuan "Jabatan Baru"-nya (CreateJabatanPage) langsung approved tanpa lewat
 * approval flow - lihat JobPositionService::autoApproveJobPosition(). Sengaja hardcode di kode
 * (bukan tabel `settings` yang bisa diubah lewat UI) karena daftar ini jarang berubah dan cuma
 * developer yang boleh mengubahnya, bukan HR/admin lewat halaman pengaturan.
 *
 */
enum SkipJobPositionApprovalCompanyEnum: string
{
    case RSU_SYIFA_MEDIKA_BANJARBARU = '01k8271xa4nq8cezg3dq9vfr14';

    public static function skips(?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return collect(self::cases())->contains(fn (self $c) => $c->value === $companyId);
    }
}
