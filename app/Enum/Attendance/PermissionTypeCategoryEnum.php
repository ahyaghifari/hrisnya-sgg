<?php

namespace App\Enum\Attendance;

/**
 * Kategori tetap `m_permission_types` — dipakai PayrollService buat cari pengajuan izin
 * subtipe tertentu tanpa cocok nama (fragile). Cuma diisi kalau company-nya sudah konfirmasi
 * jenis izinnya (sejauh ini cuma Holding, lihat planning payroll Fase 1 §eksekusi).
 */
enum PermissionTypeCategoryEnum: string
{
    case IZIN_TERLAMBAT_MASUK = 'izin_terlambat_masuk';
    case IZIN_PULANG_CEPAT = 'izin_pulang_cepat';
    case IZIN_SEHARIAN = 'izin_seharian';
    case IZIN_MENINGGALKAN_JAM_KERJA = 'izin_meninggalkan_jam_kerja';

    public function label(): string
    {
        return match ($this) {
            self::IZIN_TERLAMBAT_MASUK => 'Izin Terlambat Masuk',
            self::IZIN_PULANG_CEPAT => 'Izin Pulang Cepat',
            self::IZIN_SEHARIAN => 'Izin Seharian',
            self::IZIN_MENINGGALKAN_JAM_KERJA => 'Izin Meninggalkan Jam Kerja',
        };
    }
}
