<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PositionAllowance;
use App\Models\User;

/**
 * Lookup langsung matriks pangkat x kelas. Tidak ketemu baris = skip (0 baris), wajar kalau
 * finance belum isi kombinasi itu — bukan error.
 */
class TunjanganJabatanCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $pangkatId = $user->profile?->pangkat_id;
        $kelasId = $user->profile?->kelas_id;

        if (! $pangkatId || ! $kelasId) {
            return;
        }

        $allowance = PositionAllowance::query()
            ->where('pangkat_id', $pangkatId)
            ->where('kelas_id', $kelasId)
            ->first();

        if (! $allowance || $allowance->amount <= 0) {
            return;
        }

        $amount = (float) $allowance->amount;

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::TUNJANGAN_JABATAN->label(),
            'type' => PayrollComponentEnum::TUNJANGAN_JABATAN->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'source_type' => PayrollComponentEnum::TUNJANGAN_JABATAN->value,
        ]);

        $context->addEarning($amount);
    }
}
