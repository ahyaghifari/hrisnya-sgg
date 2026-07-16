<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * gaji_pokok = pangkat_salaries.base_salary * (1 + pangkat.class_increment_rate * steps)
 * steps = kelas.level user - pangkat.start_kelas_level (dibatasi minimal 0)
 *
 * ADDITIVE, bukan compound — dikonfirmasi user (analisis-payroll-4 §7.3): naik 2x kelas =
 * +16% dari base, BUKAN base*1.08^2.
 */
class GajiPokokCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $pangkat = $user->profile?->pangkat;
        $kelas = $user->profile?->kelas;

        if (! $pangkat || ! $kelas) {
            return;
        }

        $pangkatSalary = $pangkat->pangkatSalary;

        // Skip aman kalau fondasi datanya belum lengkap — Gaji Pokok Rp0 yang ke-generate
        // diam-diam itu bug serius, jangan asumsikan 0 sebagai default "aman".
        if (! $pangkatSalary || $kelas->level === null || $pangkat->start_kelas_level === null || $pangkat->class_increment_rate === null) {
            return;
        }

        $steps = max(0, $kelas->level - $pangkat->start_kelas_level);
        $amount = round((float) $pangkatSalary->base_salary * (1 + (float) $pangkat->class_increment_rate * $steps), 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::GAJI_POKOK->label(),
            'type' => PayrollComponentEnum::GAJI_POKOK->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => "Base Rp" . number_format((float) $pangkatSalary->base_salary, 0, ',', '.') . ", kelas +{$steps} langkah",
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);

        $context->addEarning($amount);
    }
}
