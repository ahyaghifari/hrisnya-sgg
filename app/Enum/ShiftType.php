<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ShiftType: string implements HasLabel
{
    case PAGI = 'pagi';
    case SIANG = 'siang';
    case MALAM = 'malam';

    /** Shift jaga panggilan - pilihan roster BIASA, assignable langsung sama seperti Pagi/Siang/
     * Malam (revisi 2026-09-14, lihat planning/2026-09-14-oncall-middle-redesign.md - desain
     * awal 2026-09-13 sempat menjadikan ini cuma didapat lewat Extra Shift saat libur, DIHAPUS).
     * Dihitung fleksibel: TIDAK ADA konsep telat/pulang cepat sama sekali, jam masuk bebas
     * total, target durasi dari `max_work_minutes` - lihat
     * ShiftScheduleCalculator::calculateOncall(). TIDAK terlibat Extra Shift sama sekali
     * (cuma Pagi/Siang/Malam yang boleh, lihat ExtraShiftRulePage::getShiftTypeOptionsProperty()). */
    case ONCALL = 'oncall';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PAGI => 'Pagi',
            self::SIANG => 'Siang/Sore',
            self::MALAM => 'Malam',
            self::ONCALL => 'Oncall',
        };
    }
}
