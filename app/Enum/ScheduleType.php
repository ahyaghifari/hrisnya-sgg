<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum ScheduleType: string implements HasLabel
{
    case FIXED = 'fixed';
    case FLEXIBLE = 'flexible';
    case SHIFT = 'shift';
    /** Instalasi Bedah Sentral: beberapa masuk-pulang per hari, jam kerja = jumlah pasangan scan. */
    case IBS = 'ibs';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIXED => 'Jadwal Tetap',
            self::FLEXIBLE => 'Jadwal Fleksibel',
            self::SHIFT => 'Jadwal Shift',
            self::IBS => 'Jadwal IBS',
        };
    }
}
