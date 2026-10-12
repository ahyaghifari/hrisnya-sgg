<?php

namespace App\Services\Attendance;

use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\User;
use Carbon\Carbon;

/**
 * Resolusi jadwal untuk 1 (user, tanggal) - roster harian Shift menang kalau ada (paling
 * spesifik per-tanggal), fallback ke ScheduleAssignment (Fixed/Flexible, personal lalu jabatan,
 * lewat ScheduleAssignment::resolveForUser() yang menghormati effective_from/effective_until).
 * Dipakai bareng oleh AccessLogScheduleReader (pipeline batch access_logs) dan UI real-time
 * (kartu "Presensi Hari Ini" di Dashboard/Data Diri) - extracted dari AccessLogScheduleReader
 * supaya tidak duplikat logic di 2 tempat.
 */
class ScheduleResolver
{
    /**
     * ScheduleShiftAssignment pakai trait BelongsToCompany (global scope company_id = company
     * USER LOGIN, bukan company $targetUser) - tanpa dibuka, roster shift karyawan company lain
     * kesaring diam-diam walau datanya ADA di DB, bikin shift "tidak kedeteksi" (ExtraShiftPage)
     * atau kalender kosong (JadwalKaryawanPage) buat user yang PUNYA izin lihat_perusahaan_lain
     * (dilaporkan user 2026-09-16). Dibuka HANYA kalau company target beda dari company user
     * login DAN user login memang berwenang (canFilterByCompanyForSystem) - sama persis pattern
     * ScheduleShiftRosterTab::shiftAssignmentQuery(), bukan dibuka buat semua orang. Auth::user()
     * bisa null (console/queue - AccessLogScheduleReader dsb) - BelongsToCompany sendiri sudah
     * skip scope total kalau runningInConsole(), jadi aman dibiarkan tanpa bypass eksplisit di sini.
     */
    private function shiftAssignmentQuery(User $targetUser): \Illuminate\Database\Eloquent\Builder
    {
        $query = ScheduleShiftAssignment::query();
        $actor = \Illuminate\Support\Facades\Auth::user();

        if ($actor
            && $targetUser->company_id !== $actor->company_id
            && $actor->canFilterByCompanyForSystem(\App\Enum\CustomPermissionEnum::SISTEM_ABSENSI)) {
            $query->withoutGlobalScope('company');
        }

        return $query;
    }

    /**
     * @return array{type:string,is_off_day:bool,config?:object,schedule?:\App\Models\Shift\Schedule,label:string}|null
     */
    public function resolveForUserDate(User $user, string $date, bool $ignoreEffectiveDate = false): ?array
    {
        $shiftAssignment = $this->shiftAssignmentQuery($user)
            ->with(['shiftType.schedule', 'draftShiftType.schedule'])
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        // Status pengajuan (draft in_review / approved) TIDAK dibedakan (instruksi user 2026-10-01):
        // dihitung selama sel tidak kosong. Draft terisi dipakai dulu, kalau draft kosong pakai
        // kolom approved. Dua-duanya kosong = tidak ada roster, jatuh ke Fixed/Flexible di bawah.
        // ponytail: draft yang akhirnya ditolak tidak membatalkan presensi yang sudah terbentuk
        // dari draft itu, tambahkan resweep di jalur tolak kalau perlu.
        $useDraft  = $shiftAssignment && ($shiftAssignment->draft_off_day || $shiftAssignment->draftShiftType);
        $offDay    = $useDraft ? $shiftAssignment->draft_off_day : $shiftAssignment?->off_day;
        $shiftType = $useDraft ? $shiftAssignment->draftShiftType : $shiftAssignment?->shiftType;
        $isEmpty   = $shiftAssignment?->has_draft && ! $useDraft && ! $offDay && ! $shiftType;

        if ($shiftAssignment && ! $isEmpty) {
            if ($offDay || ! $shiftType) {
                return ['type' => 'shift', 'is_off_day' => true, 'label' => 'Shift (roster off_day)'];
            }

            return [
                'type'       => 'shift',
                'is_off_day' => false,
                'config'     => $shiftType,
                'schedule'   => $shiftType->schedule,
                'label'      => 'Shift - ' . $shiftType->name,
            ];
        }

        // $ignoreEffectiveDate: bypass effective_from (demo/tes pipeline access_logs lama, Extra Shift
        // di tanggal sebelum assignment dibuat).
        $assignment = ScheduleAssignment::resolveForUser($user, $date, $ignoreEffectiveDate);

        if (! $assignment) {
            return null;
        }

        $schedule  = $assignment->schedule;
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        if ($schedule->schedule_type === 'fixed') {
            $day = ScheduleFixedDay::where('schedule_id', $schedule->id)->where('day_of_week', $dayOfWeek)->first();
            if (! $day) {
                return null;
            }

            return [
                'type'       => 'fixed',
                'is_off_day' => $day->is_off_day,
                'config'     => $day,
                'schedule'   => $schedule,
                'label'      => "Fixed - {$schedule->name}",
            ];
        }

        if ($schedule->schedule_type === 'flexible') {
            $day = ScheduleFlexibleDay::where('schedule_id', $schedule->id)->where('day_of_week', $dayOfWeek)->first();
            if (! $day) {
                return null;
            }

            return [
                'type'       => 'flexible',
                'is_off_day' => $day->is_off_day,
                'config'     => $day,
                'schedule'   => $schedule,
                'label'      => "Flexible - {$schedule->name}",
            ];
        }

        return null;
    }

    /**
     * Jumlah HARI KERJA user dalam rentang [start, end] inklusif — hari yang jatuh pada
     * off_day jadwal user (Fixed/Flexible: hari libur per day_of_week; Shift: roster off_day)
     * TIDAK dihitung. Dipakai buat total_days pengajuan CUTI yang lebih dari 1 tanggal.
     *
     * Tanggal yang jadwalnya tidak bisa diresolve — user Shift yang tanggal itu belum diisi
     * roster (n/a / null), atau user tanpa assignment sama sekali — TETAP dihitung (bukan
     * dianggap libur). Lebih aman kelebihan daripada kurang; approver masih bisa revisi manual.
     *
     * ponytail: hari libur nasional (tabel Holidays) belum ikut dikecualikan di sini — user
     * minta khusus "berdasarkan jadwal". Tambahkan cek Holidays kalau nanti diperlukan.
     */
    public function workingDaysBetween(User $user, string $start, string $end): int
    {
        $cursor = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($end)->startOfDay();

        if ($last->lt($cursor)) {
            [$cursor, $last] = [$last->copy(), $cursor->copy()];
        }

        $count = 0;

        for (; $cursor->lte($last); $cursor->addDay()) {
            // ponytail: null = jadwal tak bisa diresolve (Shift belum diroster / tanpa
            // assignment) -> dihitung. resolveForUserDate juga menandai off_day untuk baris
            // roster tanpa shiftType; kalau kasus itu ternyata perlu ikut dihitung, tangani di
            // sini, jangan ubah resolver (dipakai juga oleh pipeline absensi).
            $resolved = $this->resolveForUserDate($user, $cursor->toDateString());

            if ($resolved === null || ! $resolved['is_off_day']) {
                $count++;
            }
        }

        return $count;
    }
}
