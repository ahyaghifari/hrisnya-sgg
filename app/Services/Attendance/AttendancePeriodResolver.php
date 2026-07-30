<?php

namespace App\Services\Attendance;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Resolusi "periode Izin" (batas awal/akhir) dari 2 setting statis per company
 * (AttendanceSettingEnum::PERIOD_START_DAY/PERIOD_END_DAY) — CUMA dipakai buat kuota Izin.
 * Cuti Tahunan TIDAK lagi pakai resolver ini, sudah pindah ke bulan kalender polos (lihat
 * AttendaceReqService::getCutiTahunanPeriodeUsage) — lihat
 * knowledge/markdown/rencana-izin-empat-kategori.md §9.
 *
 * Default start=1/end=31 = ikut kalender biasa (tanggal 1 s/d akhir bulan). Kalau end < start,
 * periode nyambung ke bulan berikutnya (misal start=26, end=25 -> 26 bulan ini s/d 25 bulan
 * depan). Tanggal di luar jumlah hari bulan berjalan (mis. end=31 di Februari) di-clamp ke
 * hari terakhir bulan itu.
 *
 * Pengajuan yang membentang lintas 2 periode dihitung berdasar bulan $anchorDate saja
 * (disederhanakan sengaja) — lihat planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §3.
 */
class AttendancePeriodResolver
{
    /** @return array{0: Carbon, 1: Carbon} */
    public static function resolve(Carbon $anchorDate, ?string $companyId): array
    {
        $startDay = (int) Setting::resolve(
            AttendanceSettingEnum::PERIOD_START_DAY->value,
            $companyId,
            default: (int) AttendanceSettingEnum::PERIOD_START_DAY->default()
        );
        $endDay = (int) Setting::resolve(
            AttendanceSettingEnum::PERIOD_END_DAY->value,
            $companyId,
            default: (int) AttendanceSettingEnum::PERIOD_END_DAY->default()
        );

        $startMonth = $anchorDate->day >= min($startDay, $anchorDate->daysInMonth)
            ? $anchorDate->copy()->startOfMonth()
            : $anchorDate->copy()->subMonthNoOverflow()->startOfMonth();

        $periodStart = $startMonth->copy()->addDays(min($startDay, $startMonth->daysInMonth) - 1);

        $endMonth = $endDay >= $startDay ? $startMonth : $startMonth->copy()->addMonthNoOverflow();
        $periodEnd = $endMonth->copy()->addDays(min($endDay, $endMonth->daysInMonth) - 1);

        return [$periodStart, $periodEnd];
    }
}
