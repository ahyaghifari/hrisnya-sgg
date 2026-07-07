<?php

namespace App\Enum\Task;

use Filament\Support\Contracts\HasLabel;

enum TaskRiskLevel: string implements HasLabel
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Rendah',
            self::MEDIUM => 'Sedang',
            self::HIGH => 'Tinggi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LOW => 'success',
            self::MEDIUM => 'warning',
            self::HIGH => 'danger',
        };
    }
}
