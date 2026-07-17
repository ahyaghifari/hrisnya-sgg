<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Shift\ShiftOvertimeCosts;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User;
use Carbon\Carbon;

/**
 * Metode ditentukan setting `lembur_metode` (company-scoped, lihat requiredSettings() di
 * PayrollComponentEnum::LEMBUR). "shift" (default): logic lama PayrollService::
 * handleOvertimeComponent(), berbasis shift (ShiftScheduleAssignments + ShiftOvertimeCosts).
 * "per_pangkat": 1 rate/jam per pangkat (pangkat_lembur_rates, pola sama transport_allowances)
 * — dipakai company yang tidak butuh matriks shift, cukup 1 angka per pangkat. Rumus TL/TM
 * dari Excel Holding (analisis-payroll-4 §3.6, base 3,5jt/3jt + multiplier clamp + pembagi
 * hari) SENGAJA TIDAK dipakai — user eksplisit minta struktur sesederhana Tunjangan
 * Transportasi, bukan formula itu.
 */
class LemburCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $metode = Setting::resolve('lembur_metode', $user->company_id, default: 'shift');

        $overtimeRequests = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'overtime')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('overtimeDetails', function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date]);
            })
            ->with(['overtimeDetails' => function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date]);
            }])
            ->get();

        if ($overtimeRequests->isEmpty()) {
            return;
        }

        if ($metode === 'per_pangkat') {
            $this->calculatePerPangkat($user, $payroll, $context, $overtimeRequests);

            return;
        }

        foreach ($overtimeRequests as $request) {
            foreach ($request->overtimeDetails as $detail) {
                $this->processDetail($user, $payroll, $context, $detail);
            }
        }
    }

    private function calculatePerPangkat(User $user, Payroll $payroll, PayrollContext $context, $overtimeRequests): void
    {
        $rate = $user->profile?->pangkat?->lemburRate;

        if (! $rate || $rate->amount_per_hour <= 0) {
            return;
        }

        $minimumJam = (int) Setting::resolve('lembur_minimum_jam', $user->company_id, default: 2);

        foreach ($overtimeRequests as $request) {
            foreach ($request->overtimeDetails as $detail) {
                $this->processDetailPerPangkat($payroll, $context, $detail, (float) $rate->amount_per_hour, $minimumJam);
            }
        }
    }

    private function processDetailPerPangkat(Payroll $payroll, PayrollContext $context, AttendanceRequestOvertimeDetails $detail, float $ratePerHour, int $minimumJam): void
    {
        $hours = (float) $detail->total_hours;

        if ($hours < $minimumJam) {
            return;
        }

        $amount = round($ratePerHour * $hours, 2);

        if ($amount <= 0) {
            return;
        }

        $dateLabel = Carbon::parse($detail->date)->locale('id')->isoFormat('D MMMM YYYY');

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => "Lembur - {$dateLabel}",
            'type' => PayrollComponentEnum::LEMBUR->type()->value,
            'quantity' => $hours,
            'unit' => 'jam',
            'unit_amount' => $ratePerHour,
            'amount' => $amount,
            'note' => sprintf('Rate: Rp%s / jam (per pangkat)', number_format($ratePerHour, 0, ',', '.')),
            'source_type' => PayrollComponentEnum::LEMBUR->value,
        ]);

        $context->addEarning($amount);
    }

    private function processDetail(User $user, Payroll $payroll, PayrollContext $context, $detail): void
    {
        $actualMinutes = round((float) $detail->total_hours * 60, 2);

        if ($actualMinutes <= 0) {
            return;
        }

        $date = Carbon::parse($detail->date);

        $shiftAssignment = ShiftScheduleAssignments::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        $overtimeCost = ShiftOvertimeCosts::query()
            ->where('company_id', $user->company_id)
            ->when($shiftAssignment?->shift_id, fn ($q) => $q->where('shift_id', $shiftAssignment->shift_id))
            ->when($detail->overtime_type_id, fn ($q) => $q->where('overtime_type_id', $detail->overtime_type_id))
            ->first();

        if (! $overtimeCost || ! $overtimeCost->overtime_rate || ! $overtimeCost->maximum_overtime_hours) {
            return;
        }

        $maxMinutes = (float) $overtimeCost->maximum_overtime_hours * 60;
        $cappedMinutes = min($actualMinutes, $maxMinutes);

        $ratePerMinute = (float) $overtimeCost->overtime_rate / $maxMinutes;
        $amount = round($ratePerMinute * $cappedMinutes, 2);

        if ($amount <= 0) {
            return;
        }

        $totalMins = (int) round($actualMinutes);
        $labelHours = intdiv($totalMins, 60);
        $labelMins = $totalMins % 60;
        $durationLabel = trim(($labelHours > 0 ? "{$labelHours} jam" : '') . ($labelMins > 0 ? " {$labelMins} menit" : ''));

        $dateLabel = $date->locale('id')->isoFormat('D MMMM YYYY');

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => "Lembur - {$dateLabel} ({$durationLabel})",
            'type' => PayrollComponentEnum::LEMBUR->type()->value,
            'quantity' => round($cappedMinutes / 60, 4),
            'unit' => 'jam',
            'unit_amount' => round($ratePerMinute * 60, 4),
            'amount' => $amount,
            'note' => sprintf(
                'Rate: Rp%s / %d jam, Aktual: %.0f menit, Dihitung: %.0f menit',
                number_format((float) $overtimeCost->overtime_rate, 0, ',', '.'),
                $overtimeCost->maximum_overtime_hours,
                $actualMinutes,
                $cappedMinutes
            ),
            'source_type' => PayrollComponentEnum::LEMBUR->value,
        ]);

        $context->addEarning($amount);
    }
}
