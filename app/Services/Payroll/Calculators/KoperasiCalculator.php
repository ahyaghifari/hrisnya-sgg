<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;

class KoperasiCalculator extends AbstractManualLineCalculator
{
    protected function componentValue(): string
    {
        return PayrollComponentEnum::KOPERASI->value;
    }

    protected function componentLabel(): string
    {
        return PayrollComponentEnum::KOPERASI->label();
    }

    protected function componentType(): string
    {
        return PayrollComponentEnum::KOPERASI->type()->value;
    }
}
