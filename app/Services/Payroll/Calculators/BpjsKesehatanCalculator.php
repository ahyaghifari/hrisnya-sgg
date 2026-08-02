<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\BpjsDependent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;

class BpjsKesehatanCalculator extends AbstractBpjsCalculator
{
    /**
     * Bulan pertama (Tanggal Bergabung jatuh di tengah periode ini, sama syaratnya dengan
     * prorate Gaji Pokok di GajiPokokCalculator) — BPJS Kesehatan TIDAK dipotong sama sekali
     * (Rp0), bukan diprorate. Bulan-bulan berikutnya potong normal.
     */
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $joiningDate = $user->profile?->joining_date ? Carbon::parse($user->profile->joining_date) : null;

        if ($joiningDate && $joiningDate->gt($period->start_date) && $joiningDate->lte($period->end_date)) {
            return;
        }

        parent::calculate($user, $payroll, $period, $context);
    }

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
