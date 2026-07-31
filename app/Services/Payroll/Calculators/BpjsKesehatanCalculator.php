<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\BpjsDependent;
use App\Models\User;

class BpjsKesehatanCalculator extends AbstractBpjsCalculator
{
    protected function rateSetting(): GlobalPayrollSettingEnum
    {
        return GlobalPayrollSettingEnum::BPJS_KESEHATAN_RATE;
    }

    /**
     * 1 (karyawan sendiri) + jumlah tanggungan (istri/suami, anak, orang tua, dst) — lihat
     * BpjsDependent::count. Ketenagakerjaan tidak override ini, tetap x1.
     */
    protected function multiplier(User $user): float
    {
        $dependent = BpjsDependent::where('user_id', $user->id)->where('is_active', true)->first();

        return 1.0 + ($dependent->count ?? 0);
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
