<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\Calculators\Concerns\ResolvesProrateDivisor;
use Carbon\Carbon;

/**
 * Basis bersama 2 teknik potongan Cuti Melahirkan/Keguguran (setting `cuti_melahirkan_metode`,
 * dipilih di halaman Komponen Gaji, lihat CutiCalculator dispatcher) - hitung L (hari kalender
 * cuti efektif DALAM periode ini), lalu serahkan nominal potongan ke rumus masing-masing
 * teknik (lihat CutiPerKomponenCalculator/CutiThpCalculator). Dipecah jadi Calculator terpisah
 * (bukan 1 class dengan percabangan private method) - pola sama AbstractIzinSeharianCalculator
 * - supaya masing-masing teknik punya rumusnya sendiri yang jelas dan gampang ditest terpisah.
 *
 * Cuti Tahunan/Haid TIDAK kena sama sekali - kalau maternityLeaveDaysInPeriod() balik 0 (tidak
 * ada cuti Melahirkan/Keguguran APPROVED yang overlap periode ini), calculate() no-op total,
 * sama seperti Cuti biasa (dikonfirmasi user - "Cuti TIDAK ADA POTONGAN").
 */
abstract class AbstractCutiMelahirkanCalculator implements PayrollComponentCalculator
{
    use ResolvesProrateDivisor;

    /** Nominal potongan (Rp per periode) - null/<=0 = tidak ada yang dipotong. */
    abstract protected function resolveAmount(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context, int $divisor, int $days): ?float;

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $days = $this->maternityLeaveDaysInPeriod($user, $period);

        if ($days <= 0) {
            return;
        }

        $divisor = $this->prorateDivisor($period, Carbon::parse($period->start_date));

        if ($divisor <= 0) {
            return;
        }

        $amount = $this->resolveAmount($user, $payroll, $period, $context, $divisor, $days);

        if ($amount === null || $amount <= 0) {
            return;
        }

        $amount = round($amount, 2);

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::CUTI->label(),
            'type' => PayrollComponentEnum::CUTI->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => "Potongan Cuti Melahirkan/Keguguran ({$days} hari kalender dari {$divisor} hari efektif)",
            'source_type' => PayrollComponentEnum::CUTI->value,
        ]);

        $context->addDeduction($amount);
    }

    /**
     * L: total hari KALENDER cuti Melahirkan/Keguguran (APPROVED) yang overlap periode ini -
     * dijumlah kalau kebetulan ada >1 baris leaveDetails yang overlap periode yang sama
     * (jarang, tapi bukan mustahil). Beda dari total_days tersimpan di request (itu
     * keseluruhan pengajuan, bisa melintasi periode lain - cuti melahirkan 90 hari kalender
     * hampir pasti melintasi 2-3 periode payroll, lihat LeaveTypeCategoryEnum::countsCalendarDays()).
     */
    private function maternityLeaveDaysInPeriod(User $user, PayrollPeriod $period): int
    {
        $periodStart = Carbon::parse($period->start_date);
        $periodEnd = Carbon::parse($period->end_date);

        $details = AttendanceRequestLeaveDetails::query()
            ->whereHas('request', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('type', 'leave')
                ->where('status', AttendanceReqStatus::APPROVED->value))
            ->whereHas('leaveType', fn ($q) => $q
                ->whereIn('category', [
                    LeaveTypeCategoryEnum::MELAHIRKAN->value,
                    LeaveTypeCategoryEnum::KEGUGURAN->value,
                ]))
            ->where('start_date', '<=', $period->end_date)
            ->where('end_date', '>=', $period->start_date)
            ->get(['start_date', 'end_date']);

        $days = 0;

        foreach ($details as $detail) {
            $start = Carbon::parse($detail->start_date)->max($periodStart);
            $end = Carbon::parse($detail->end_date)->min($periodEnd);

            if ($end->gte($start)) {
                $days += $start->diffInDays($end) + 1;
            }
        }

        return $days;
    }
}
