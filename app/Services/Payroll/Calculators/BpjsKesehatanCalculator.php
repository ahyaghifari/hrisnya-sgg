<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Employee\EmployeeBpjsDependent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;

class BpjsKesehatanCalculator extends AbstractBpjsCalculator
{
    /**
     * Bulan pertama (Tanggal Bergabung jatuh di tengah periode ini, sama syaratnya dengan
     * prorate Gaji Pokok di GajiPokokCalculator) — BPJS Kesehatan TIDAK dipotong sama sekali
     * (Rp0), bukan diprorate. Bulan-bulan berikutnya potong normal. Tetap bikin baris Rp0 +
     * catatan penjelasan (2026-08-02) - biar kelihatan alasannya, bukan hilang total dari slip.
     */
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $joiningDate = $user->employee?->joining_date ? Carbon::parse($user->employee->joining_date) : null;

        if ($joiningDate && $joiningDate->gt($period->start_date) && $joiningDate->lte($period->end_date)) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => PayrollComponentEnum::BPJS_KESEHATAN->defaultRowName(null),
                'type' => PayrollComponentEnum::BPJS_KESEHATAN->type()->value,
                'quantity' => 0,
                'amount' => 0,
                'note' => "Karyawan baru bergabung {$joiningDate->format('d-m-Y')} — belum wajib BPJS bulan pertama",
                'source_type' => PayrollComponentEnum::BPJS_KESEHATAN->value,
            ]);

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
     * EmployeeBpjsDependent::count. Ketenagakerjaan tidak override ini, tetap x1.
     */
    protected function multiplier(User $user, Payroll $payroll): float
    {
        $dependent = EmployeeBpjsDependent::where('employee_id', $payroll->employee_id)->where('is_active', true)->first();

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
