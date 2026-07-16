<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;

class BpjsKetenagakerjaanCalculator extends AbstractBpjsCalculator
{
    protected function rateSetting(): GlobalPayrollSettingEnum
    {
        return GlobalPayrollSettingEnum::BPJS_KETENAGAKERJAAN_RATE;
    }

    protected function componentValue(): string
    {
        return PayrollComponentEnum::BPJS_KETENAGAKERJAAN->value;
    }

    protected function componentLabel(): string
    {
        return PayrollComponentEnum::BPJS_KETENAGAKERJAAN->label();
    }

    protected function componentType(): string
    {
        return PayrollComponentEnum::BPJS_KETENAGAKERJAAN->type()->value;
    }
}
