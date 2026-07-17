<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Incentive\Incentive;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * Metode ditentukan setting `insentif_metode` (company-scoped, lihat requiredSettings() di
 * PayrollComponentEnum::INSENTIF). "master" (Holding): nominal tetap, input manual ke tabel
 * `incentives`, dibaca di sini. "import" (RSU, perhitungan dinamis): TIDAK dibaca di sini sama
 * sekali — insentif ditulis LANGSUNG ke payroll_items lewat App\Imports\Payroll\IncentiveImport,
 * SETELAH payroll digenerate (lihat app/Livewire/Keuangan/ImportIncentivesPage.php). Kalau
 * Calculator ini tetap jalan pas metode "import", akan bentrok dengan PayrollItem yang sudah
 * ditulis import (dobel source_type=insentif) — makanya skip total.
 */
class IncentiveCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $metode = Setting::resolve('insentif_metode', $period->company_id, default: 'master');

        if ($metode !== 'master') {
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
}
