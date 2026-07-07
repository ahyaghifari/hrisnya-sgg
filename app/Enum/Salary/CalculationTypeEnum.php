<?php

namespace App\Enum\Salary;

enum CalculationTypeEnum: string
{
    case FIXED = 'fixed';
    case FORMULA = 'formula';
    case PERCENTAGE = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixed',
            self::FORMULA => 'Formula',
            self::PERCENTAGE => 'Percentage',
        };
    }
}
