<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\TenureAllowance;
use App\Models\User;
use App\Services\Payroll\Calculators\Concerns\ProratesFirstMonth;
use Carbon\Carbon;

/** Ikut diprorate bulan pertama kalau karyawan baru bergabung di tengah periode
 * (ProratesFirstMonth, dikonfirmasi user 2026-08-05) - terpisah dari `$years` yang cuma
 * dipakai nentuin bracket (karyawan baru = 0 tahun, biasanya bracket terendah). */
class TunjanganMasaKerjaCalculator implements PayrollComponentCalculator
{
    use ProratesFirstMonth;

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $joiningDate = $user->profile?->joining_date;

        if (! $joiningDate) {
            return;
        }

        // abs() + cast int - Carbon 3 diffInYears() sekarang bisa balikin float BERTANDA
        // (mis. -0.07 buat karyawan yang baru bergabung beberapa hari sebelum $period->start_date
        // dibandingkan), beda dari Carbon 2 yang selalu int absolut. Tanpa ini, karyawan baru
        // gagal cocok ke bracket manapun (min_years<=$years jadi false untuk $years negatif).
        $years = (int) abs(Carbon::parse($joiningDate)->diffInYears(Carbon::parse($period->start_date)));

        $bracket = TenureAllowance::query()
            ->where('company_id', $period->company_id)
            ->where('min_years', '<=', $years)
            ->where(fn ($q) => $q->whereNull('max_years')->orWhere('max_years', '>=', $years))
            ->orderByDesc('min_years')
            ->first();

        if (! $bracket || $bracket->amount <= 0) {
            return;
        }

        $amount = (float) $bracket->amount;
        $note = "Masa kerja {$years} tahun";

        if ($prorate = $this->firstMonthProrate($user, $period)) {
            $amount = round($amount * $prorate['ratio'], 2);
            $note .= ', ' . $prorate['note'];
        }

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA->label(),
            'type' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => $note,
            'source_type' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA->value,
        ]);

        $context->addEarning($amount);
    }
}
