<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ShiftType: string implements HasLabel
{
    case PAGI = 'pagi';
    case SIANG = 'siang';
    case MALAM = 'malam';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PAGI => 'Pagi',
            self::SIANG => 'Siang/Sore',
            self::MALAM => 'Malam',
        };
    }
}
