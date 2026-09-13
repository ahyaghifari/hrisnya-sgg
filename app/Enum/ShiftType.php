<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ShiftType: string implements HasLabel
{
    case PAGI = 'pagi';
    case SIANG = 'siang';
    case MALAM = 'malam';

    /** Shift jaga panggilan - bukan pilihan roster, cuma didapat lewat Extra Shift saat karyawan
     * libur. Dihitung fleksibel (target durasi, bukan window ketat), lihat
     * ShiftScheduleCalculator::calculateOncall(). Approved -> menimpa roster hari itu, lihat
     * ExtraShiftAssignment::syncOncallRoster(). */
    case ONCALL = 'oncall';

    /** Shift jam tetap di antara Pagi dan Siang - pilihan roster biasa, dihitung sama seperti
     * Pagi/Siang/Malam. Tidak terlibat Extra Shift. */
    case MIDDLE = 'middle';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PAGI => 'Pagi',
            self::SIANG => 'Siang/Sore',
            self::MALAM => 'Malam',
            self::ONCALL => 'Oncall',
            self::MIDDLE => 'Middle',
        };
    }
}
