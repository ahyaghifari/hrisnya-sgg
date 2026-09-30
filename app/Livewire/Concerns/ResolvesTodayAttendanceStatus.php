<?php

namespace App\Livewire\Concerns;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Livewire\ExtraShiftTodayCard;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\Holidays;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Attendance\ShiftAttendanceReader;
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
     *     attendance_window: ?array{type: string, from: string, until: string, start_time?: string, end_time?: string, checkin_buffer_minutes?: int, checkout_buffer_minutes?: int, late_tolerance_minutes?: int, late_threshold?: string, late_checkin_deadline?: ?string},
     * }
     */
    protected function todayAttendanceStatus(User $user, ?AttendanceRecords $attendance = null): array
    {
        $date = $this->resolveCardDate($user);

        // $attendance dari pemanggil selalu baris HARI INI - buang kalau kartu lagi nempel ke
        // shift kemarin (lihat resolveCardDate), biar dimuat ulang sesuai tanggal kartu.
        if ($attendance && Carbon::parse($attendance->date)->toDateString() !== $date) {
            $attendance = null;
        }

        return $this->buildTodayAttendanceStatus($user, $attendance, $date) + ['date' => $date];
    }

    /**
     * Tanggal yang dipakai kartu. Biasanya hari ini, TAPI shift malam (is_overnight) kemarin yang
     * sudah check-in & belum check-out tetap jadi "hari ini" sampai jendela pulangnya habis
     * (end_time + checkout_buffer besok pagi) - sama dengan aturan AccessLogScheduleReader yang
     * menyimpan presensi overnight di tanggal MULAI shift dan "meminjam" scan pulang besoknya.
     * Tanpa ini, lewat tengah malam kartu langsung ganti ke hari baru padahal karyawan masih
     * dalam shift-nya. Sudah check-out = kartu langsung ganti ke hari ini.
     */
    protected function resolveCardDate(User $user): string
    {
        $now = now();
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();

        // "Terbuka" = sudah check-in, belum check-out. Shift normal tersimpan di attendance_records,
        // extra/double shift di extra_shift_attendance_records (per shift, jadi double shift dengan
        // jeda bisa punya leg pertama sudah tutup tapi leg extra malam masih terbuka).
        $normalOpen = AttendanceRecords::where('user_id', $user->id)
            ->where('date', $yesterday)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->exists();

        $extra = app(ShiftAttendanceReader::class)->find($user, $yesterday);
        $extraOpen = $extra?->shiftType?->is_overnight
            && ExtraShiftAttendanceRecord::where('extra_shift_assignment_id', $extra->id)
                ->whereNotNull('check_in_time')
                ->whereNull('check_out_time')
                ->exists();

        if (! $normalOpen && ! $extraOpen) {
            return $today;
        }

        // Shift yang berhak "meminjam" scan pulang hari ini: yang masih terbuka DAN overnight.
        // Jendela pulangnya ikut yang paling akhir.
        $overnight = [];
        if ($normalOpen) {
            $resolved = app(ScheduleResolver::class)->resolveForUserDate($user, $yesterday);
            $config = $resolved['config'] ?? null;

            if (($resolved['type'] ?? null) === 'shift' && ! ($resolved['is_off_day'] ?? false) && ($config->is_overnight ?? false)) {
                $overnight[] = $config;
            }
        }

        if ($extraOpen) {
            $overnight[] = $extra->shiftType;
        }

        foreach ($overnight as $shift) {
            $windowEnd = Carbon::parse($today . ' ' . $shift->end_time)->addMinutes((int) $shift->checkout_buffer_minutes);

            if ($now->lt($windowEnd)) {
                return $yesterday;
            }
        }

        return $today;
    }

    private function buildTodayAttendanceStatus(User $user, ?AttendanceRecords $attendance, string $todayStr): array
    {

        $resolved = app(ScheduleResolver::class)->resolveForUserDate($user, $todayStr);

        // Gabungan sama info Extra/Double Shift hari ini (dulu kartu "Extra Shift/Double Shift
        // Hari Ini" terpisah, ExtraShiftTodayCard) jadi 1 kartu Presensi Hari Ini - instruksi
        // user 2026-10-16: cuma dicek untuk jadwal tipe Shift, jadwal Tetap/Fleksibel tidak
        // perlu query tambahan ini sama sekali (dianggap tidak pernah ada extra/double shift).
        $extraShiftToday = ($resolved['type'] ?? null) === 'shift'
            ? $this->resolveExtraShiftToday($user, $todayStr)
            : null;

        // Jendela presensi valid hari ini (kapan paling awal boleh scan masuk, kapan paling
        // akhir boleh scan pulang, dst) - dihitung persis sama dengan FixedScheduleCalculator/
        // FlexibleScheduleCalculator supaya kartu ini tidak pernah menyesatkan (klaim jendela
        // beda dari yang benar-benar dipakai buat validasi scan). Detail lengkap (bukan cuma
        // label ringkas) buat modal "Detail Jendela Presensi" di today-attendance-card.
        $attendanceWindow = $this->resolveAttendanceWindow($resolved);

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
                'attendance_window'        => null,
                'extra_shift'              => $extraShiftToday,
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
                'attendance_window'        => null,
                'extra_shift'              => $extraShiftToday,
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
                // Izin seharian = tidak masuk sama sekali, jendela presensi tidak relevan. Izin
                // biasa (sebagian hari) tetap perlu presensi di sisa jamnya.
                'attendance_window'        => $isFullDay ? null : $attendanceWindow,
                'extra_shift'              => $extraShiftToday,
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
                'attendance_window'        => null,
                'extra_shift'              => $extraShiftToday,
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
            'attendance_window'        => $attendanceWindow,
            'extra_shift'              => $extraShiftToday,
        ];
    }

    /** Extra/Double Shift APPROVED hari ini (kalau ada), dibentuk lewat
     * ExtraShiftTodayCard::build() - satu sumber kebenaran yang sama dipakai widget lama itu
     * (masih ada & dites sendiri, tapi tidak lagi dirender terpisah di Beranda). null kalau
     * tidak ada. */
    private function resolveExtraShiftToday(User $user, string $todayStr): ?array
    {
        $extraShift = ExtraShiftAssignment::where('user_id', $user->id)
            ->whereDate('date', $todayStr)
            ->where('status', 'approved')
            ->with(['shiftType', 'createdBy'])
            ->first();

        return $extraShift ? ExtraShiftTodayCard::build($user, $todayStr, $extraShift) : null;
    }

    /**
     * Detail jendela presensi valid hari ini, dihitung PERSIS sama dengan calculator yang
     * dipakai pipeline access_logs (jangan hardcode ulang rumus lain di sini):
     * - Fixed/Shift: start_time - checkin_buffer_minutes s/d end_time + checkout_buffer_minutes
     *   (FixedScheduleCalculator::$earliestCheckIn/$latestCheckOut), plus late_tolerance_minutes
     *   (jam mulai dianggap telat).
     * - Flexible: schedules.flexible_checkin_from s/d flexible_checkin_until (jendela level
     *   schedule, bukan per-hari - FlexibleScheduleCalculator), plus late_checkin_deadline
     *   per-hari kalau diisi. Null kalau field yang relevan kosong, berarti jadwal ini memang
     *   tidak membatasi jendela check-in.
     */
    private function resolveAttendanceWindow(?array $resolved): ?array
    {
        if (! $resolved || ($resolved['is_off_day'] ?? false) || ! isset($resolved['config'])) {
            return null;
        }

        $config = $resolved['config'];

        if (in_array($resolved['type'], ['fixed', 'shift'], true)) {
            if (! $config->start_time || ! $config->end_time) {
                return null;
            }

            $start = Carbon::createFromFormat('H:i:s', $config->start_time);
            $end   = Carbon::createFromFormat('H:i:s', $config->end_time);

            return [
                'type'                    => $resolved['type'],
                'from'                    => $start->copy()->subMinutes($config->checkin_buffer_minutes)->format('H:i'),
                'until'                   => $end->copy()->addMinutes($config->checkout_buffer_minutes)->format('H:i'),
                'start_time'              => $start->format('H:i'),
                'end_time'                => $end->format('H:i'),
                'checkin_buffer_minutes'  => (int) $config->checkin_buffer_minutes,
                'checkout_buffer_minutes' => (int) $config->checkout_buffer_minutes,
                'late_tolerance_minutes'  => (int) $config->late_tolerance_minutes,
                'late_threshold'          => $start->copy()->addMinutes($config->late_tolerance_minutes)->format('H:i'),
            ];
        }

        if ($resolved['type'] === 'flexible') {
            $schedule = $resolved['schedule'] ?? null;

            if (! $schedule?->flexible_checkin_from || ! $schedule?->flexible_checkin_until) {
                return null;
            }

            return [
                'type'                   => 'flexible',
                'from'                   => substr($schedule->flexible_checkin_from, 0, 5),
                'until'                  => substr($schedule->flexible_checkin_until, 0, 5),
                'late_checkin_deadline'  => $config->late_checkin_deadline ? substr($config->late_checkin_deadline, 0, 5) : null,
            ];
        }

        return null;
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
