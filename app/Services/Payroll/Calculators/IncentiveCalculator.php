<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Incentive\Incentive;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Holding: nominal tetap, input manual (sudah jalan apa adanya, tidak berubah). RSU: fitur
 * import terpisah (di luar scope Calculator ini) yang tetap menulis ke tabel `incentives`
 * yang sama — jadi Calculator ini otomatis mencakup keduanya begitu data ada di tabel.
 */
class IncentiveCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $incentives = Incentive::query()
            ->where('user_id', $user->id)
            ->where('is_processed', false)
            ->whereBetween('incentive_date', [$period->start_date, $period->end_date])
            ->get();

        if ($incentives->isEmpty()) {
            return;
        }

        $totalAmount = (float) $incentives->sum('amount');

        if ($totalAmount <= 0) {
            return;
        }

        $names = $incentives->pluck('name')->filter()->unique()->implode(', ');

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::INSENTIF->label(),
            'type' => PayrollComponentEnum::INSENTIF->type()->value,
            'quantity' => 1,
            'amount' => $totalAmount,
            'note' => $names ?: null,
            'source_type' => PayrollComponentEnum::INSENTIF->value,
        ]);

        $context->addEarning($totalAmount);

        Incentive::query()->whereIn('id', $incentives->pluck('id'))->update(['is_processed' => true]);
    }
}
