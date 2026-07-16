<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\Payroll\PayrollComponentEnum;

class IzinTerlambatPulangCepatCalculator extends AbstractIzinFlatCalculator
{
    protected function permissionTypeCategories(): array
    {
        return [
            PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value,
            PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value,
        ];
    }

    protected function componentValue(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->value;
    }

    protected function componentLabel(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->label();
    }

    protected function componentType(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->type()->value;
    }
}
