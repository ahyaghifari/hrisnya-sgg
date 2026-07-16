<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\TransportAllowance;
use App\Models\User;

class TunjanganTransportasiCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $kelasId = $user->profile?->kelas_id;

        if (! $kelasId) {
            return;
        }

        $allowance = TransportAllowance::query()->where('kelas_id', $kelasId)->first();

        if (! $allowance || $allowance->amount <= 0) {
            return;
        }

        $amount = (float) $allowance->amount;

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->label(),
            'type' => PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'source_type' => PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->value,
        ]);

        $context->addEarning($amount);
    }
}
