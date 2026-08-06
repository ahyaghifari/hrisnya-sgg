<?php

namespace App\Services\Attendance\Calculators;

use App\Enum\AttendanceStatus;
use App\Models\Shift\ScheduleShiftType;
use App\Services\Attendance\Calculators\Concerns\ComputesWorkDayWeight;
use Carbon\Carbon;

/**
 * Tahap 5 (Shift) pipeline access_logs -> jadwal. Sama pola dengan FixedScheduleCalculator
 * (config ScheduleShiftType field-nya identik untuk telat/pulang-cepat/lembur/jam-kerja), 2 beda:
 * - ScheduleShiftType TIDAK punya min_work_minutes (lihat migration schedule_shift_types) - jadi
 *   tidak ada fitur checkout_rejected di sini, checkout selalu diterima apa adanya.
 * - is_overnight: shift yang melewati tengah malam (mis. 22:00-06:00). end_time digeser +1 hari
 *   kalau <= start_time, dan check_out digeser +1 hari kalau lebih awal dari check_in (pola sama
 *   dengan FlexibleScheduleCalculator).
 */
class ShiftScheduleCalculator
{
    use ComputesWorkDayWeight;

    public function calculate(array $entries): array
    {
        return array_map(fn (array $entry) => $this->calculateOne($entry), $entries);
    }

    private function calculateOne(array $entry): array
    {
        /** @var ScheduleShiftType $config */
        $config = $entry['config'];

        $start = Carbon::createFromFormat('H:i:s', $config->start_time);
        $end   = Carbon::createFromFormat('H:i:s', $config->end_time);

        if ($config->is_overnight && $end->lte($start)) {
            $end->addDay();
        }

        $ci = Carbon::createFromFormat('H:i:s', $entry['check_in']);

        $earliestCheckIn = $start->copy()->subMinutes($config->checkin_buffer_minutes);
        $latestCheckOut  = $end->copy()->addMinutes($config->checkout_buffer_minutes);

        $lateThreshold = $start->copy()->addMinutes($config->late_tolerance_minutes);
        $lateMinutes   = $ci->gt($lateThreshold) ? (int) $lateThreshold->diffInMinutes($ci) : 0;

        $earlyLeaveMinutes = 0;
        $workingMinutes    = null;
        $overtimeMinutes   = $this->overtimeBefore($config, $start, $ci);
        $outOfWindow       = $ci->lt($earliestCheckIn);
        $checkOut          = $entry['check_out'];

        if ($checkOut) {
            $co = Carbon::createFromFormat('H:i:s', $checkOut);
            if ($config->is_overnight && $co->lt($ci)) {
                $co->addDay();
            }

            $earlyThreshold = $end->copy()->subMinutes($config->early_leave_tolerance_minutes);
            $earlyLeaveMinutes = $co->lt($earlyThreshold) ? (int) $co->diffInMinutes($earlyThreshold) : 0;

            $workingMinutes = max(0, (int) $ci->diffInMinutes($co) - $config->break_minutes);
            if ($config->max_work_minutes) {
                $workingMinutes = min($workingMinutes, $config->max_work_minutes);
            }

            $overtimeMinutes += $this->overtimeAfter($config, $end, $co);
            $outOfWindow      = $outOfWindow || $co->gt($latestCheckOut);
        }

        $status = $lateMinutes > 0 ? AttendanceStatus::LATE->value : AttendanceStatus::PRESENT->value;

        return $entry + [
            'late_minutes'        => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'working_minutes'     => $workingMinutes,
            'overtime_minutes'    => $overtimeMinutes > 0 ? $overtimeMinutes : null,
            'work_day_weight'     => $this->workDayWeight($config, $workingMinutes),
            'status'              => $status,
            'out_of_window'       => $outOfWindow,
        ];
    }

    private function overtimeBefore(ScheduleShiftType $config, Carbon $start, Carbon $ci): int
    {
        if ($config->overtime_before_max <= 0) {
            return 0;
        }

        $countedFrom = $start->copy()->subMinutes($config->overtime_before_max);
        $countedTo   = $start->copy()->subMinutes($config->overtime_before_min);
        $effectiveCi = $ci->gt($countedFrom) ? $ci : $countedFrom;

        if ($effectiveCi->gte($countedTo)) {
            return 0;
        }

        return max(0, (int) $effectiveCi->diffInMinutes($countedTo) - $config->overtime_before_break);
    }

    private function overtimeAfter(ScheduleShiftType $config, Carbon $end, Carbon $co): int
    {
        if ($config->overtime_after_max <= 0) {
            return 0;
        }

        $countedFrom = $end->copy()->addMinutes($config->overtime_after_min);
        $countedTo   = $end->copy()->addMinutes($config->overtime_after_max);
        $effectiveCo = $co->lt($countedTo) ? $co : $countedTo;

        if ($effectiveCo->lte($countedFrom)) {
            return 0;
        }

        return max(0, (int) $countedFrom->diffInMinutes($effectiveCo) - $config->overtime_after_break);
    }
}
