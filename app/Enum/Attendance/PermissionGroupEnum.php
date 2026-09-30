<?php

namespace App\Enum\Attendance;

use App\Enum\Attendance\Concerns\HasCompanyLabelOverride;

/**
 * 4 kelompok besar Izin yang dipilih user di level 1 form pengajuan (`SubmitPermissionRequest`).
 * Konsep grouping ini murni UI, bukan kolom database - jenis izin (PermissionTypeEnum) tetap flat,
 * cuma dikelompokkan lewat categories() di bawah. Kelompok MENINGGALKAN_JAM_KERJA & KHUSUS punya
 * pilihan level 2 (>1 kategori), TIDAK_MASUK_SEHARIAN & SAKIT flat (1 kategori).
 */
enum PermissionGroupEnum: string
{
    use HasCompanyLabelOverride;

    case MENINGGALKAN_JAM_KERJA = 'meninggalkan_jam_kerja';
    case TIDAK_MASUK_SEHARIAN = 'tidak_masuk_seharian';
    case SAKIT = 'sakit';
    case KHUSUS = 'khusus';

    /** $companyId null = label default (semua company lain). 2 company override (lihat
     * HasCompanyLabelOverride) pakai penamaan sendiri buat Tidak Masuk/Sakit - instruksi user
     * 2026-09-30. */
    public function label(?string $companyId = null): string
    {
        if (self::usesLabelOverride($companyId)) {
            $override = match ($this) {
                self::TIDAK_MASUK_SEHARIAN => 'Izin Tidak Masuk / Sakit tanpa surat',
                self::SAKIT => 'Izin Sakit Bersurat',
                default => null,
            };

            if ($override !== null) {
                return $override;
            }
        }

        return match ($this) {
            self::MENINGGALKAN_JAM_KERJA => 'Izin Meninggalkan Jam Kerja',
            self::TIDAK_MASUK_SEHARIAN => 'Izin Tidak Masuk',
            self::SAKIT => 'Izin Sakit',
            self::KHUSUS => 'Izin Khusus',
        };
    }

    /** @return PermissionTypeCategoryEnum[] */
    public function categories(): array
    {
        return match ($this) {
            self::MENINGGALKAN_JAM_KERJA => [
                PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK,
                PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT,
                PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA,
            ],
            self::TIDAK_MASUK_SEHARIAN => [PermissionTypeCategoryEnum::IZIN_SEHARIAN],
            self::SAKIT => [PermissionTypeCategoryEnum::IZIN_SAKIT],
            self::KHUSUS => [PermissionTypeCategoryEnum::IZIN_KHUSUS],
        };
    }

    /**
     * Kelompok yang butuh dropdown level 2 di form. Meninggalkan Jam Kerja punya banyak
     * KATEGORI (3), Khusus punya banyak JENIS (PermissionTypeEnum) tapi 1 kategori sama
     * (dibedakan lewat jenis/labelnya) - keduanya tetap butuh dropdown, TIDAK bisa dideteksi
     * dari count(categories()) (itu bug lama: Khusus keliru dianggap flat karena cuma 1 kategori).
     */
    public function hasSubKategori(): bool
    {
        return $this === self::MENINGGALKAN_JAM_KERJA || $this === self::KHUSUS;
    }

    /** Kelompok yang tanggalnya rentang (start_date-end_date), bukan 1 date. */
    public function isRentangTanggal(): bool
    {
        return $this === self::SAKIT || $this === self::KHUSUS;
    }

    /** Kelompok yang boleh upload lampiran. */
    public function bolehLampiran(): bool
    {
        return $this === self::SAKIT || $this === self::KHUSUS;
    }

    public static function fromCategory(PermissionTypeCategoryEnum $category): self
    {
        foreach (self::cases() as $group) {
            if (in_array($category, $group->categories(), true)) {
                return $group;
            }
        }

        throw new \ValueError("Kategori {$category->value} tidak masuk kelompok manapun.");
    }
}
