<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Shift\ShiftOvertimeCosts;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User;
use Carbon\Carbon;

/**
 * Logic sama persis PayrollService::handleOvertimeComponent() lama — berbasis shift
 * (ShiftScheduleAssignments + ShiftOvertimeCosts), TIDAK berubah. Rumus khusus Holding
 * (TL/TM, lihat analisis-payroll-4 §3.6) masih ditunda — arti TL/TM & aturan >2 jam belum
 * jelas — jadi company yang butuh itu tetap pakai mekanisme shift generik ini dulu.
 */
class LemburCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
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

        foreach ($overtimeRequests as $request) {
            foreach ($request->overtimeDetails as $detail) {
                $this->processDetail($user, $payroll, $context, $detail);
            }
        }
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
