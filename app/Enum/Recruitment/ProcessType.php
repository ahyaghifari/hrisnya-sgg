<?php

namespace App\Enum\Recruitment;

use Filament\Support\Contracts\HasLabel;

enum ProcessType: string implements HasLabel
{
    case TEST = 'test';
    case INTERVIEW = 'interview';
    case OFFERING = 'offering';
    case MCU = 'mcu';
    case SIGNING = 'signing';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::TEST => 'Tes',
            self::INTERVIEW => 'Interview',
            self::OFFERING => 'Offering',
            self::MCU => 'Medical Check Up',
            self::SIGNING => 'Penandatanganan Kontrak',
            self::OTHER => 'Lainnya',
        };
    }
}
