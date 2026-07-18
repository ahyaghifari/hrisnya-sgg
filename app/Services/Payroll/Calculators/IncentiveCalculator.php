<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Incentive\Incentive;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * Metode ditentukan setting `insentif_metode` (company-scoped, lihat requiredSettings() di
 * PayrollComponentEnum::INSENTIF). "master" (Holding): nominal tetap, input manual ke tabel
 * `incentives`, dibaca di sini. "import" (RSU, perhitungan dinamis): dibaca dari
 * `payroll_manual_lines` (component=insentif) — App\Imports\Payroll\IncentiveImport menulis ke
 * situ (bukan langsung ke payroll_items lagi), jadi baris ini ikut ke-generate ulang normal
 * kalau periode di-regenerate, sama seperti Koperasi/Bon.
 */
class IncentiveCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $metode = Setting::resolve('insentif_metode', $period->company_id, default: 'master');

        if ($metode !== 'master') {
            $this->calculateFromImport($user, $payroll, $period, $context);

            return;
        }

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

    private function calculateFromImport(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $line = PayrollManualLine::query()
            ->where('user_id', $user->id)
            ->where('payroll_period_id', $period->id)
            ->where('component', PayrollComponentEnum::INSENTIF->value)
            ->first();

        if (! $line || (float) $line->amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::INSENTIF->label(),
            'type' => PayrollComponentEnum::INSENTIF->type()->value,
            'quantity' => 1,
            'amount' => $line->amount,
            'note' => $line->note,
            'source_type' => PayrollComponentEnum::INSENTIF->value,
        ]);

        $context->addEarning((float) $line->amount);
    }
}
