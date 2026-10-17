<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\PayrollJasaUnitDetail;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Input manual lewat `payroll_jasa_unit_details` (halaman "Input Jasa Unit"), tidak ada rumus otomatis.
 * Beda dari AbstractManualLineCalculator: tiap baris punya nama jasanya sendiri (JasaUnitType),
 * bukan 1 label tetap per komponen — jadi tiap baris jadi PayrollItem terpisah dengan namanya
 * masing-masing (Jasa Perujuk Rawat, Jasa Ambulan, dst), bukan digabung.
 */
class JasaUnitCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $details = PayrollJasaUnitDetail::with('jasaUnitType')
            ->where('employee_id', $payroll->employee_id)
            ->where('payroll_period_id', $period->id)
            ->get();

        foreach ($details as $detail) {
            $amount = (float) $detail->amount;

            if ($amount <= 0 || ! $detail->jasaUnitType) {
                continue;
            }

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $detail->jasaUnitType->name,
                'type' => PayrollComponentTypeEnum::EARNING->value,
                'quantity' => 1,
                'amount' => $amount,
                'note' => $detail->note,
                'source_type' => PayrollComponentEnum::JASA_UNIT->value,
            ]);

            $context->addEarning($amount);
        }
    }
}
