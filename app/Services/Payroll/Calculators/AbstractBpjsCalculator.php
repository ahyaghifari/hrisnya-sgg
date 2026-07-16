<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * Basis potongan: pangkat.bpjs_basis === 'ump' -> GlobalPayrollSettingEnum::UMP (selalu
 * global), 'declared_value' -> Setting('bpjs_declared_value') (selalu company-scoped, lihat
 * PayrollComponentEnum::requiredSettings()). Skip aman (0 baris) kalau bpjs_basis belum diisi
 * ATAU setting basisnya belum diisi — JANGAN asumsikan basis 0 sebagai default.
 */
abstract class AbstractBpjsCalculator implements PayrollComponentCalculator
{
    abstract protected function rateSetting(): GlobalPayrollSettingEnum;
    abstract protected function componentValue(): string;
    abstract protected function componentLabel(): string;
    abstract protected function componentType(): string;

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $bpjsBasis = $user->profile?->pangkat?->bpjs_basis;

        if (! $bpjsBasis) {
            return;
        }

        if ($bpjsBasis === 'ump') {
            $basis = Setting::resolve(GlobalPayrollSettingEnum::UMP->value, null);
        } elseif ($bpjsBasis === 'declared_value') {
            $basis = Setting::resolve('bpjs_declared_value', $period->company_id);
        } else {
            return;
        }

        if (! $basis || $basis <= 0) {
            return;
        }

        $rate = Setting::resolve($this->rateSetting()->value, null);

        if (! $rate || $rate <= 0) {
            return;
        }

        $amount = round((float) $basis * (float) $rate / 100, 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => $this->componentLabel(),
            'type' => $this->componentType(),
            'quantity' => $rate,
            'unit' => 'persen',
            'unit_amount' => $basis,
            'amount' => $amount,
            'note' => "Basis: {$bpjsBasis}",
            'source_type' => $this->componentValue(),
        ]);

        $context->addDeduction($amount);
    }
}
