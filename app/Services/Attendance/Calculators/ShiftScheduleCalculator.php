<?php

namespace App\Services\Attendance\Calculators;

use App\Enum\AttendanceStatus;
use App\Enum\ShiftType;
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
 *
 * shift_type === Oncall dikecualikan dari alur di atas - lihat calculateOncall().
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

        if ($config->shift_type === ShiftType::ONCALL->value) {
            return $this->calculateOncall($entry, $config);
        }

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

    /**
     * Oncall dihitung fleksibel (target durasi kerja, bukan window start/end ketat) - instruksi
     * user 2026-09-13. TIDAK ADA KONSEP TELAT SAMA SEKALI (instruksi user 2026-09-14, revisi
     * dari versi awal yang masih hitung late_minutes dari start_time+late_tolerance_minutes) -
     * jam masuk Oncall bebas total, tidak dibandingkan ke jam berapa pun, `late_minutes` SELALU
     * 0 dan `status` tidak pernah jadi LATE karena jam masuk. checkin_buffer_minutes juga jadi
     * tidak relevan (dulu buat out_of_window, sekarang selalu false - konsisten "tidak ada
     * jendela jam" buat Oncall). Efeknya otomatis nyambung ke payroll: KeterlambatanCalculator
     * cuma baca AttendanceRecords.status='late'/late_minutes>0 apa adanya, tidak
     * spesifik-shift_type - begitu Oncall tidak pernah menghasilkan status itu, potongan telat
     * otomatis tidak pernah kena ke hari Oncall, TANPA perlu ubah kode payroll sama sekali.
     * TIDAK ada early_leave (tidak ada end_time resmi buat dibandingkan, sama seperti
     * FlexibleScheduleCalculator). max_work_minutes di sini berarti TARGET (bukan cap mutlak) -
     * kelebihannya jadi kandidat lembur lewat overtime_after_min/break/max, formula sama persis
     * dengan FlexibleScheduleCalculator::calculateOne() supaya konsisten.
     */
    private function calculateOncall(array $entry, ScheduleShiftType $config): array
    {
        $ci = Carbon::createFromFormat('H:i:s', $entry['check_in']);

        $workingMinutes  = null;
        $overtimeMinutes = null;
        $checkOut        = $entry['check_out'];

        if ($checkOut) {
            $co = Carbon::createFromFormat('H:i:s', $checkOut);
            if ($co->lt($ci)) {
                $co->addDay();
            }

            $rawMinutes = max(0, (int) $ci->diffInMinutes($co) - $config->break_minutes);
            $target     = $config->max_work_minutes;

            if ($target > 0 && $rawMinutes > $target) {
                $workingMinutes    = $target;
                $overtimeCandidate = $rawMinutes - $target;

                if ($config->overtime_after_max > 0 && $overtimeCandidate >= $config->overtime_after_min) {
                    $overtimeMinutes = max(0, min($overtimeCandidate, $config->overtime_after_max) - $config->overtime_after_break);
                }
            } else {
                $workingMinutes = $rawMinutes;
            }
        }

        return $entry + [
            'late_minutes'        => 0,
            'early_leave_minutes' => 0,
            'working_minutes'     => $workingMinutes,
            'overtime_minutes'    => $overtimeMinutes,
            'work_day_weight'     => $this->workDayWeight($config, $workingMinutes),
            'status'              => AttendanceStatus::PRESENT->value,
            'out_of_window'       => false,
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
