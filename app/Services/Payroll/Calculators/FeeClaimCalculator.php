<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\FeeItem;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * Always-on — TIDAK digate lewat company_payroll_components (lihat PayrollComponentEnum::
 * alwaysOn()). Murni data-driven: jalan kalau ada FeeItem belum lunas, berhenti sendiri kalau
 * tidak ada. Logic sama persis PayrollService::handleFeeItemsComponent() lama, cuma rate
 * pajak pindah dari salary_components (category=pajak_jasa_dokter) ke
 * Setting::resolve('pajak_jasa_dokter_rate').
 */
class FeeClaimCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $feeItems = FeeItem::query()
            ->where('user_id', $user->id)
            ->where('is_paid', false)
            ->whereHas('submission', function ($q) use ($period) {
                $q->where('company_id', $period->company_id)
                    ->whereBetween('date', [$period->start_date, $period->end_date]);
            })
            ->get();

        if ($feeItems->isEmpty()) {
            return;
        }

        $totalFee = 0.0;

        foreach ($feeItems as $item) {
            $amount = (float) $item->amount;

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $item->name,
                'type' => PayrollComponentEnum::FEE_CLAIM->type()->value,
                'quantity' => 1,
                'amount' => $amount,
                'source_type' => PayrollComponentEnum::FEE_CLAIM->value,
            ]);

            $context->addEarning($amount);
            $totalFee += $amount;
        }

        $taxRate = Setting::resolve('pajak_jasa_dokter_rate', $period->company_id, default: 0);

        if ($taxRate > 0 && $totalFee > 0) {
            $taxAmount = round($totalFee * (float) $taxRate / 100, 2);

            if ($taxAmount > 0) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'name' => PayrollComponentEnum::PAJAK_JASA_DOKTER->label(),
                    'type' => PayrollComponentEnum::PAJAK_JASA_DOKTER->type()->value,
                    'quantity' => $taxRate,
                    'unit' => 'persen',
                    'amount' => $taxAmount,
                    'source_type' => PayrollComponentEnum::PAJAK_JASA_DOKTER->value,
                ]);

                $context->addDeduction($taxAmount);
            }
        }

        FeeItem::query()->whereIn('id', $feeItems->pluck('id'))->update(['is_paid' => true]);
    }
}
