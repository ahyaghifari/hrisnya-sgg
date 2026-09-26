<?php

namespace App\Livewire\Concerns;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\Holidays;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;

/**
 * Status presensi "hari ini" untuk 1 user - gabungan jadwal (Fixed/Flexible/Shift), hari libur
 * (dari jadwal ATAU m_holidays), cuti/izin yang approved hari ini, dan data attendance_records
 * asli kalau ada. Dipakai bareng oleh Dashboard (kartu Presensi Hari Ini di home) dan
 * EmployeeStatistics (kartu sama di tab Presensi & Absensi, Data Diri) - sengaja 1
 * tempat biar aturan prioritas (Libur > Cuti > Izin Seharian > Izin biasa > normal) konsisten
 * di kedua tampilan, bukan 2 logic terpisah yang bisa mencong.
 */
trait ResolvesTodayAttendanceStatus
{
    /**
     * @return array{
     *     schedule_type_label: ?string,
     *     schedule_name: ?string,
     *     schedule_detail: ?string,
     *     state: 'holiday'|'leave'|'full_day_permission'|'permission'|'no_schedule'|'normal',
     *     color: 'red'|'blue'|'yellow'|'green'|'gray'|'plain',
     *     note: ?string,
     *     attendance: ?AttendanceRecords,
     *     late_minutes_label: ?string,
     *     flexible_min_work_label: ?string,
     *     flexible_target_checkout: ?string,
     * }
     */
    protected function todayAttendanceStatus(User $user, ?AttendanceRecords $attendance = null): array
    {
        $todayStr = Carbon::today()->toDateString();

        $resolved = app(ScheduleResolver::class)->resolveForUserDate($user, $todayStr);

        $scheduleTypeLabel = match ($resolved['type'] ?? null) {
            'fixed'    => 'Jadwal Tetap',
            'flexible' => 'Jadwal Fleksibel',
            'shift'    => 'Jadwal Shift',
            default    => null,
        };

        $scheduleName = $resolved['schedule']->name ?? null;

        // Detail singkat yang paling relevan per tipe: Fixed/Shift jam kerjanya (kapan aja
        // scan-nya harus di rentang ini), Flexible target minimal jam kerja (kapan aja boleh
        // scan, yang penting total jam kerjanya cukup).
        $scheduleDetail = match ($resolved['type'] ?? null) {
            'fixed' => isset($resolved['config'])
                ? substr($resolved['config']->start_time, 0, 5) . '–' . substr($resolved['config']->end_time, 0, 5)
                : null,
            'shift' => isset($resolved['config'])
                ? $resolved['config']->name . ' ' . substr($resolved['config']->start_time, 0, 5) . '–' . substr($resolved['config']->end_time, 0, 5)
                : null,
            'flexible' => isset($resolved['config']) && $resolved['config']->min_work_minutes > 0
                ? 'min. ' . $this->formatMinutesAsHours($resolved['config']->min_work_minutes)
                : null,
            default => null,
        };

        $attendance ??= AttendanceRecords::where('user_id', $user->id)->where('date', $todayStr)->first();

        // Terlambat ditampilkan sebagai "X menit" kalau < 1 jam, atau "X jam Y menit" kalau
        // >= 1 jam - lebih gampang dibaca daripada mentah "353 menit".
        $lateMinutesLabel = $attendance && $attendance->late_minutes > 0
            ? $this->formatMinutesAsHours($attendance->late_minutes)
            : null;

        // Jadwal Flexible: target minimal jam kerja + jam pulang yang harus dicapai supaya
        // memenuhi target itu, dihitung dari jam masuk aktual hari ini. Target checkout IKUT
        // menambahkan break_minutes - FlexibleScheduleCalculator mengurangi waktu istirahat dari
        // working_minutes yang benar-benar dihitung, jadi karyawan butuh hadir LEBIH LAMA dari
        // sekadar min_work_minutes buat menutupi jam istirahatnya juga. Tanpa ini, target
        // checkout yang ditampilkan lebih awal dari yang sebenarnya dibutuhkan (2026-08-05).
        $flexibleMinWorkLabel   = null;
        $flexibleTargetCheckout = null;
        if (($resolved['type'] ?? null) === 'flexible' && ($resolved['config']->min_work_minutes ?? 0) > 0) {
            $flexibleMinWorkLabel = $this->formatMinutesAsHours($resolved['config']->min_work_minutes);

            if ($attendance?->check_in_time) {
                $flexibleTargetCheckout = Carbon::parse($attendance->check_in_time)
                    ->addMinutes($resolved['config']->min_work_minutes + $resolved['config']->break_minutes)
                    ->format('H:i');
            }
        }

        $holiday      = Holidays::where('date', $todayStr)->first();
        $isScheduleOff = (bool) ($resolved['is_off_day'] ?? false);

        if ($holiday || $isScheduleOff) {
            return [
                'schedule_type_label' => $scheduleTypeLabel,
                'schedule_name'       => $scheduleName,
                'schedule_detail'     => $scheduleDetail,
                'state'          => 'holiday',
                // Tanggal merah / libur nasional (m_holidays) tetap merah. Kalau cuma jadwalnya
                // yang off (mis. hari libur rotasi shift), kartu netral/putih - bukan "alarm".
                'color'          => $holiday ? 'red' : 'plain',
                'note'           => $holiday?->name ?? 'Hari libur sesuai jadwal',
                'attendance'     => $attendance,
                'late_minutes_label'       => $lateMinutesLabel,
                'flexible_min_work_label'  => $flexibleMinWorkLabel,
                'flexible_target_checkout' => $flexibleTargetCheckout,
            ];
        }

        $leave = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'leave')
            ->where('status', 'approved')
            ->whereHas('leaveDetails', fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('end_date')->where('start_date', '<=', $todayStr)->where('end_date', '>=', $todayStr))
                ->orWhere(fn ($q2) => $q2->whereNull('end_date')->where('start_date', $todayStr))
            )
            ->with('leaveDetails')
            ->first();

        if ($leave) {
            return [
                'schedule_type_label' => $scheduleTypeLabel,
                'schedule_name'       => $scheduleName,
                'schedule_detail'     => $scheduleDetail,
                'state'          => 'leave',
                'color'          => 'blue',
                'note'           => 'Cuti - ' . ($leave->leaveDetails->first()?->leave_type ?? 'Cuti'),
                'attendance'     => $attendance,
                'late_minutes_label'       => $lateMinutesLabel,
                'flexible_min_work_label'  => $flexibleMinWorkLabel,
                'flexible_target_checkout' => $flexibleTargetCheckout,
            ];
        }

        $permission = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'permission')
            ->where('status', 'approved')
            ->whereHas('permissionDetails', fn ($q) => $q->where('date', $todayStr))
            ->with(['permissionDetails' => fn ($q) => $q->where('date', $todayStr)])
            ->first();

        if ($permission) {
            $detail   = $permission->permissionDetails->first();
            $label    = $detail?->permission_type ?? 'Izin';
            $isFullDay = $detail?->category === PermissionTypeCategoryEnum::IZIN_SEHARIAN->value;

            return [
                'schedule_type_label' => $scheduleTypeLabel,
                'schedule_name'       => $scheduleName,
                'schedule_detail'     => $scheduleDetail,
                'state'          => $isFullDay ? 'full_day_permission' : 'permission',
                'color'          => $isFullDay ? 'yellow' : 'green',
                'note'           => "Izin - {$label}",
                'attendance'     => $attendance,
                'late_minutes_label'       => $lateMinutesLabel,
                'flexible_min_work_label'  => $flexibleMinWorkLabel,
                'flexible_target_checkout' => $flexibleTargetCheckout,
            ];
        }

        // Tidak ada jadwal ketemu sama sekali (belum di-assign lewat ScheduleAssignment/roster
        // Shift) - beda dari "libur" (yang berarti ADA jadwal tapi hari ini off). Ganti isi
        // kartu jadi pesan eksplisit, bukan diam-diam nampilin jam presensi tanpa konteks jadwal.
        if (! $resolved) {
            return [
                'schedule_type_label' => null,
                'schedule_name'       => null,
                'schedule_detail'     => null,
                'state'               => 'no_schedule',
                'color'               => 'gray',
                'note'                => 'Anda belum memiliki jadwal, silakan hubungi pihak terkait.',
                'attendance'          => $attendance,
                'late_minutes_label'       => $lateMinutesLabel,
                'flexible_min_work_label'  => $flexibleMinWorkLabel,
                'flexible_target_checkout' => $flexibleTargetCheckout,
            ];
        }

        return [
            'schedule_type_label' => $scheduleTypeLabel,
            'schedule_name'       => $scheduleName,
            'schedule_detail'     => $scheduleDetail,
            'state'               => 'normal',
            'color'               => 'green',
            'note'                => null,
            'attendance'          => $attendance,
            'late_minutes_label'       => $lateMinutesLabel,
            'flexible_min_work_label'  => $flexibleMinWorkLabel,
            'flexible_target_checkout' => $flexibleTargetCheckout,
        ];
    }

    /**
     * 35 -> "35 menit", 420 -> "7 jam", 450 -> "7 jam 30 menit".
     */
    private function formatMinutesAsHours(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest  = $minutes % 60;

        if ($hours === 0) {
            return "{$rest} menit";
        }

        return $rest > 0 ? "{$hours} jam {$rest} menit" : "{$hours} jam";
    }
}
