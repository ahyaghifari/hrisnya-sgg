<?php

namespace App\Services\Attendance\Calculators;

use App\Enum\AttendanceStatus;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Services\Attendance\Calculators\Concerns\ComputesWorkDayWeight;
use Carbon\Carbon;

/**
 * Tahap 5 (Flexible) pipeline access_logs -> jadwal. Beda dari Fixed/Shift: Flexible tidak
 * punya start_time/end_time resmi, cuma jendela check-in (schedules.flexible_checkin_from/
 * until) dan target min/max jam kerja per hari (ScheduleFlexibleDay). Konsekuensinya
 * late_minutes/early_leave_minutes TIDAK relevan secara konsep untuk Flexible (lihat diskusi
 * kesesuaian attendance_records untuk 3 tipe jadwal) - tetap dikembalikan sebagai 0 supaya
 * bentuk hasil konsisten dengan FixedScheduleCalculator, tapi maknanya "tidak berlaku", bukan
 * "tidak telat".
 */
class FlexibleScheduleCalculator
{
    use ComputesWorkDayWeight;

    public function calculate(array $entries): array
    {
        return array_map(fn (array $entry) => $this->calculateOne($entry), $entries);
    }

    private function calculateOne(array $entry): array
    {
        /** @var ScheduleFlexibleDay $config */
        $config   = $entry['config'];
        $schedule = $entry['schedule'];

        $ci = Carbon::createFromFormat('H:i:s', $entry['check_in']);

        $withinCheckinWindow = true;
        if ($schedule->flexible_checkin_from && $schedule->flexible_checkin_until) {
            $windowFrom  = Carbon::createFromFormat('H:i:s', $schedule->flexible_checkin_from);
            $windowUntil = Carbon::createFromFormat('H:i:s', $schedule->flexible_checkin_until);
            $withinCheckinWindow = $ci->between($windowFrom, $windowUntil);
        }

        // ponytail: checkin_buffer_minutes/checkout_buffer_minutes SENGAJA tidak dipakai di sini
        // walau kolomnya ada di schedule_flexible_days - migration aslinya sudah mencatat kolom
        // itu "disediakan biar 1 struktur konsisten, meski kurang relevan buat Flexible" karena
        // jendela absensi Flexible sudah diwakili flexible_checkin_from/until di atas.

        $workingMinutes  = null;
        $overtimeMinutes = null;
        $meetsTarget     = null;

        if ($entry['check_out']) {
            $co = Carbon::createFromFormat('H:i:s', $entry['check_out']);
            if ($co->lt($ci)) {
                $co->addDay();
            }

            $rawMinutes = max(0, (int) $ci->diffInMinutes($co) - $config->break_minutes);

            // Batas mutlak dulu (max_work_minutes_cap) - di atas ini benar-benar tidak dihitung
            // sama sekali, bukan cuma "tidak dianggap lembur".
            $cappedTotal = $config->max_work_minutes_cap
                ? min($rawMinutes, $config->max_work_minutes_cap)
                : $rawMinutes;

            // max_work_minutes DI FLEXIBLE = target jam kerja normal (dipasangkan dengan
            // min_work_minutes), BUKAN batas mutlak seperti di Fixed/Shift - kelebihan dari
            // target ini yang jadi kandidat lembur, dibatasi overtime_min/max_minutes dan
            // overtime_break_minutes dikurangkan (sama pola dengan break_minutes kerja biasa).
            $target = $config->max_work_minutes;
            if ($target > 0 && $cappedTotal > $target) {
                $workingMinutes    = $target;
                $overtimeCandidate = $cappedTotal - $target;

                if ($config->overtime_max_minutes > 0 && $overtimeCandidate > $config->overtime_min_minutes) {
                    $overtimeMinutes = max(0, min($overtimeCandidate, $config->overtime_max_minutes) - $config->overtime_break_minutes);
                }
            } else {
                $workingMinutes = $cappedTotal;
            }

            $meetsTarget = ($workingMinutes + ($overtimeMinutes ?? 0)) >= $config->min_work_minutes;
        }

        // status: tidak ada konsep telat/pulang-cepat untuk Flexible (lihat docblock class ini).
        // Sama seperti Fixed, lembur auto-hitung dari raw scan TIDAK mengubah status jadi
        // 'overtime' - itu cuma berlaku untuk pengajuan lembur yang sudah disetujui.
        // half_day_threshold_minutes/absent_threshold_minutes TIDAK mengubah status juga - cuma
        // menghasilkan work_day_weight terpisah (dipakai rekap payroll), sama seperti Fixed.
        $status = AttendanceStatus::PRESENT->value;

        // work_day_weight dibandingkan ke TOTAL kerja aktual (working + overtime), bukan cuma
        // working_minutes yang sudah di-cap ke target - lembur tetap kerja sungguhan.
        $totalMinutes = $workingMinutes === null ? null : $workingMinutes + ($overtimeMinutes ?? 0);

        return $entry + [
            'late_minutes'        => 0,
            'early_leave_minutes' => 0,
            'working_minutes'     => $workingMinutes,
            'overtime_minutes'    => $overtimeMinutes,
            'meets_target'        => $meetsTarget,
            'work_day_weight'     => $this->workDayWeight($config, $totalMinutes),
            'status'              => $status,
            'out_of_window'       => ! $withinCheckinWindow,
        ];
    }
}
