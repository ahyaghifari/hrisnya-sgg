<?php

namespace App\Enum\Approval;

use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;

/**
 * "Ember" jenis pengajuan absensi untuk routing alur persetujuan (kolom
 * m_approval_flows.sub_module). Cuti & Lembur 1 ember masing-masing; Izin dipecah per
 * kelompok (PermissionGroupEnum) supaya izin meninggalkan jam kerja bisa beda approver
 * final dari izin tidak masuk / cuti.
 *
 * Value izin sengaja identik dengan PermissionGroupEnum::value — bisa pakai ulang
 * PermissionGroupEnum::fromCategory() tanpa mapping tambahan.
 */
enum AttendanceApprovalBucketEnum: string
{
    case LEAVE             = 'leave';
    case OVERTIME          = 'overtime';
    case IZIN_MENINGGALKAN = 'meninggalkan_jam_kerja';
    case IZIN_TIDAK_MASUK  = 'tidak_masuk_seharian';
    case IZIN_SAKIT        = 'sakit';
    case IZIN_KHUSUS       = 'khusus';

    public function label(): string
    {
        return match ($this) {
            self::LEAVE             => 'Cuti',
            self::OVERTIME          => 'Lembur',
            self::IZIN_MENINGGALKAN => 'Izin Meninggalkan Jam Kerja',
            self::IZIN_TIDAK_MASUK  => 'Izin Tidak Masuk',
            self::IZIN_SAKIT        => 'Izin Sakit',
            self::IZIN_KHUSUS       => 'Izin Khusus',
        };
    }

    /** @return array<string, string> value => label, buat multi-select Filament & kolom tabel. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    /**
     * Ember untuk sebuah pengajuan Izin berdasarkan `category` (m_permission_types.category /
     * AttendanceRequestPermissionDetails.category). null kalau kategori kosong / tak dikenal —
     * izin lama tanpa kategori jatuh ke alur default.
     */
    public static function fromPermissionCategory(?string $category): ?self
    {
        if (! $category) {
            return null;
        }

        $cat = PermissionTypeCategoryEnum::tryFrom($category);

        return $cat ? self::from(PermissionGroupEnum::fromCategory($cat)->value) : null;
    }
}
