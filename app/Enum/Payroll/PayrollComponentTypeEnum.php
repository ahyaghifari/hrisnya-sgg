<?php

namespace App\Enum\Payroll;

enum PayrollComponentTypeEnum: string
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
