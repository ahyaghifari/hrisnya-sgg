<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\Payroll\PayrollComponentEnum;

class IzinTerlambatMasukCalculator extends AbstractIzinFlatCalculator
{
    protected function permissionTypeCategory(): string
    {
        return PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value;
    }

    protected function componentValue(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_MASUK->value;
    }

    protected function componentLabel(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_MASUK->label();
    }

    protected function componentType(): string
    {
        return PayrollComponentEnum::IZIN_TERLAMBAT_MASUK->type()->value;
    }
}
