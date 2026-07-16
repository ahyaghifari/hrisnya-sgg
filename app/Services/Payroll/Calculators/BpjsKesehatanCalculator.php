<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;

class BpjsKesehatanCalculator extends AbstractBpjsCalculator
{
    protected function rateSetting(): GlobalPayrollSettingEnum
    {
        return GlobalPayrollSettingEnum::BPJS_KESEHATAN_RATE;
    }

    protected function componentValue(): string
    {
        return PayrollComponentEnum::BPJS_KESEHATAN->value;
    }

    protected function componentLabel(): string
    {
        return PayrollComponentEnum::BPJS_KESEHATAN->label();
    }

    protected function componentType(): string
    {
        return PayrollComponentEnum::BPJS_KESEHATAN->type()->value;
    }
}
