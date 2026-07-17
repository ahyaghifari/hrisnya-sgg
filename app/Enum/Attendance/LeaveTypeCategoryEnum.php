<?php

namespace App\Enum\Attendance;

/**
 * Kategori tetap `m_leave_types` — dipakai buat cari jenis cuti tertentu (mis. Cuti Tahunan,
 * buat aturan batas akumulasi/bulan) tanpa cocok nama (fragile). Pola sama
 * PermissionTypeCategoryEnum. Cuma 1 case sejauh ini — belum ada kebutuhan bisnis buat jenis
 * cuti lain, tambah case baru kalau memang muncul aturan yang butuh identifikasi spesifik.
 */
enum LeaveTypeCategoryEnum: string
{
    case TAHUNAN = 'tahunan';

    public function label(): string
    {
        return match ($this) {
            self::TAHUNAN => 'Cuti Tahunan',
        };
    }
}
