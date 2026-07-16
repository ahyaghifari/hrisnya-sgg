<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;

class BonCalculator extends AbstractManualLineCalculator
{
    protected function componentValue(): string
    {
        return PayrollComponentEnum::BON->value;
    }

    protected function componentLabel(): string
    {
        return PayrollComponentEnum::BON->label();
    }

    protected function componentType(): string
    {
        return PayrollComponentEnum::BON->type()->value;
    }
}
