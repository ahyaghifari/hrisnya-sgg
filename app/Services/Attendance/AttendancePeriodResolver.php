<?php

namespace App\Services\Attendance;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Resolusi "periode absensi" (batas awal/akhir) dari 1 setting cutoff per company
 * (AttendanceSettingEnum::PERIOD_CUTOFF_DAY) — dipakai bareng oleh semua batas "per periode"
 * (Cuti Tahunan, Izin), bukan kalender 1-31 tetap. Default cutoff 1 = ikut kalender biasa.
 *
 * Pengajuan yang membentang lintas 2 periode dihitung berdasar bulan $anchorDate saja
 * (disederhanakan sengaja) — lihat planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §3.
 */
class AttendancePeriodResolver
{
    /** @return array{0: Carbon, 1: Carbon} */
    public static function resolve(Carbon $anchorDate, ?string $companyId): array
    {
        $cutoffDay = (int) Setting::resolve(
            AttendanceSettingEnum::PERIOD_CUTOFF_DAY->value,
            $companyId,
            default: (int) AttendanceSettingEnum::PERIOD_CUTOFF_DAY->default()
        );

        if ($cutoffDay <= 1) {
            return [$anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()];
        }

        $periodStart = $anchorDate->day >= $cutoffDay
            ? $anchorDate->copy()->day($cutoffDay)
            : $anchorDate->copy()->subMonthNoOverflow()->day($cutoffDay);

        $periodEnd = $periodStart->copy()->addMonthNoOverflow()->subDay();

        return [$periodStart, $periodEnd];
    }
}
