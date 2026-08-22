<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;

/**
 * Baca LANGSUNG dari `user_koperasi` (roster anggota aktif) tiap Generate/Regenerate - TIDAK
 * lewat payroll_manual_lines lagi (beda dari Bon/Insentif import, lihat AbstractManualLineCalculator).
 * Koperasi tidak butuh tahap "sync ke tabel perantara" karena sumbernya sendiri (is_active +
 * amount) sudah selalu 1 nilai terkini per user, tidak ada rincian per baris/periode seperti Bon
 * mode Detail (2026-08-22, instruksi user).
 */
class KoperasiCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $member = UserKoperasi::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $member) {
            return;
        }

        $amount = (float) $member->amount;

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::KOPERASI->label(),
            'type' => PayrollComponentEnum::KOPERASI->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'source_type' => PayrollComponentEnum::KOPERASI->value,
        ]);

        $context->addDeduction($amount);
    }
}
