<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Input manual finance lewat `payroll_manual_lines` (Koperasi/Bon = potongan, Insentif metode
 * "import" = pendapatan — lihat IncentiveCalculator), tidak ada rumus otomatis sama sekali.
 * Calculator ini cuma "menyalin" baris manual yang sudah diinput ke payroll_items — nama baris
 * di slip pakai `label` per baris kalau diisi (Bon mode Detail: nama potongan spesifik, mis.
 * "Angsuran Motor"), fallback ke `componentLabel()` kalau kosong (Koperasi & Bon mode Simple).
 */
abstract class AbstractManualLineCalculator implements PayrollComponentCalculator
{
    abstract protected function componentValue(): string;
    abstract protected function componentLabel(): string;
    abstract protected function componentType(): string;

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $lines = PayrollManualLine::query()
            ->where('user_id', $user->id)
            ->where('payroll_period_id', $period->id)
            ->where('component', $this->componentValue())
            ->get();

        foreach ($lines as $line) {
            $amount = (float) $line->amount;

            if ($amount <= 0) {
                continue;
            }

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $line->label ?: $this->componentLabel(),
                'type' => $this->componentType(),
                'quantity' => 1,
                'amount' => $amount,
                'note' => $line->note,
                'source_type' => $this->componentValue(),
            ]);

            if ($this->componentType() === PayrollComponentTypeEnum::EARNING->value) {
                $context->addEarning($amount);
            } else {
                $context->addDeduction($amount);
            }
        }
    }
}
