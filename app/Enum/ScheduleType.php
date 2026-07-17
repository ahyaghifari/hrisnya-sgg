<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum ScheduleType: string implements HasLabel
{
    case FIXED = 'fixed';
    case FLEXIBLE = 'flexible';
    case SHIFT = 'shift';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIXED => 'Jadwal Tetap',
            self::FLEXIBLE => 'Jadwal Fleksibel',
            self::SHIFT => 'Jadwal Shift',
        };
    }
}
