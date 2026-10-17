<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\TransportAllowance;
use App\Models\User;
use App\Services\Payroll\Calculators\Concerns\ProratesFirstMonth;

/** Ikut diprorate bulan pertama kalau karyawan baru bergabung di tengah periode
 * (ProratesFirstMonth, dikonfirmasi user 2026-08-05). Juga ikut diprorate bulan TTD kontrak
 * (Orientasi->Kontrak, instruksi user 2026-09-15, lihat contractTransitionRatio()) - Orientasi
 * tidak dapat tunjangan ini sama sekali. */
class TunjanganTransportasiCalculator implements PayrollComponentCalculator
{
    use ProratesFirstMonth;

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $kelasId = $user->employee?->kelas_id;

        if (! $kelasId) {
            return;
        }

        $allowance = TransportAllowance::query()->where('kelas_id', $kelasId)->first();

        if (! $allowance || $allowance->amount <= 0) {
            return;
        }

        $amount = (float) $allowance->amount;
        $note = null;

        if ($prorate = $this->contractTransitionRatio($user, $period) ?? $this->firstMonthProrate($user, $period)) {
            $amount = round($amount * $prorate['ratio'], 2);
            $note = ucfirst($prorate['note']);
        }

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->label(),
            'type' => PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => $note,
            'source_type' => PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->value,
        ]);

        $context->addEarning($amount);
    }
}
