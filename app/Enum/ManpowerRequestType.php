<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum ManpowerRequestType: string implements HasLabel
{
    case ADDITIONAL_QUOTA = 'additional_quota';
    case ADDITIONAL_EMPLOYEE = 'additional_employee';
    case EMPLOYEE_REPLACEMENT = 'employee_replacement';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADDITIONAL_QUOTA => 'Penambahan Kuota',
            self::ADDITIONAL_EMPLOYEE => 'Penambahan Karyawan',
            self::EMPLOYEE_REPLACEMENT => 'Penggantian Karyawan',
        };
    }
}
