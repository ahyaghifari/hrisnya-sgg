<?php

namespace App\Enum;

/**
 * Pola shift RS/klinik pada umumnya - 2 shift (Pagi/Siang) atau 3 shift (Pagi/Siang/Malam).
 * Disimpan di Schedule::shift_pattern (cuma relevan schedule_type=shift), murni informasi
 * konfigurasi buat sekarang - belum ada logic lain yang membaca nilainya.
 */
enum ShiftPatternEnum: string
{
    case TWO_SHIFT = '2_shift';
    case THREE_SHIFT = '3_shift';

    public function label(): string
    {
        return match ($this) {
            self::TWO_SHIFT => '2 Shift (Pagi, Siang)',
            self::THREE_SHIFT => '3 Shift (Pagi, Siang, Malam)',
        };
    }
}
