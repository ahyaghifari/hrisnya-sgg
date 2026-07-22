<?php

namespace App\Services\Attendance\Calculators\Concerns;

/**
 * Angka hari-kerja efektif (0/0.5/counted_work_days) dari working_minutes vs
 * half_day_threshold_minutes/absent_threshold_minutes - sama persis di ScheduleFixedDay &
 * ScheduleFlexibleDay (nama kolom identik), dipakai bareng dari 2 calculator. Lihat komentar
 * kolomnya di migration 2026_07_22_083646_add_comments_to_schedule_config_columns.php dan
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md untuk arti aslinya.
 */
trait ComputesWorkDayWeight
{
    private function workDayWeight(object $config, ?int $workingMinutes): ?float
    {
        if ($workingMinutes === null) {
            return null;
        }

        if ($config->absent_threshold_minutes > 0 && $workingMinutes < $config->absent_threshold_minutes) {
            return 0.0;
        }

        if ($config->half_day_threshold_minutes > 0 && $workingMinutes < $config->half_day_threshold_minutes) {
            return 0.5;
        }

        return (float) $config->counted_work_days;
    }
}
