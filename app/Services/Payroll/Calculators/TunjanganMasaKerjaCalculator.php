<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\TenureAllowance;
use App\Models\User;
use Carbon\Carbon;

class TunjanganMasaKerjaCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $joiningDate = $user->profile?->joining_date;

        if (! $joiningDate) {
            return;
        }

        $years = Carbon::parse($joiningDate)->diffInYears(Carbon::parse($period->start_date));

        $bracket = TenureAllowance::query()
            ->where('company_id', $period->company_id)
            ->where('min_years', '<=', $years)
            ->where(fn ($q) => $q->whereNull('max_years')->orWhere('max_years', '>=', $years))
            ->orderByDesc('min_years')
            ->first();

        if (! $bracket || $bracket->amount <= 0) {
            return;
        }

        $amount = (float) $bracket->amount;

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA->label(),
            'type' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => "Masa kerja {$years} tahun",
            'source_type' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA->value,
        ]);

        $context->addEarning($amount);
    }
}
