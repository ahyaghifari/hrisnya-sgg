<?php

namespace App\Enum\Salary;

enum SalaryComponentTypeEnum: string
{
    case EARNING = 'earning';
    case DEDUCTION = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::EARNING => 'Pendapatan',
            self::DEDUCTION => 'Potongan',
        };
    }
}

