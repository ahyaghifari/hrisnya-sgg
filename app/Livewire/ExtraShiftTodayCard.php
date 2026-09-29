<?php

namespace App\Livewire;

use App\Models\Shift\ExtraShiftAssignment;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Attendance\ShiftAttendanceReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Kartu dashboard "Extra Shift/Double Shift Hari Ini" - beritahu karyawan kalau hari ini dia
 * kebagian extra shift atau double shift, dan JAM BERAPA harus scan:
 * - Shift nempel (ShiftAttendanceReader::isAdjacent()) -> cukup 2x scan (masuk shift pertama,
 *   pulang shift kedua) - lihat ShiftAttendanceReader::splitTwoScans().
 * - Shift skip (ada jeda, mis. Pagi lalu lompat ke Malam) -> WAJIB 4x scan (masuk/pulang
 *   masing2 shift terpisah) - tidak boleh ditebak (instruksi user 2026-09-13: "kalo yang skip
 *   mungkin harusnya 4 kali absen, tapi kalo yang beterusan cukup 2 kali").
 *
 * Oncall tidak pernah lewat sini sama sekali (2026-09-14) - dia shift roster biasa yang
 * assignable langsung (schedule_shift_assignments), bukan lagi ExtraShiftAssignment - sudah
 * tercover kartu Presensi Hari Ini biasa.
 */
class ExtraShiftTodayCard extends Component
{
    public function render()
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $extraShift = ExtraShiftAssignment::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->where('status', 'approved')
            ->with(['shiftType', 'createdBy'])
            ->first();

        return view('livewire.extra-shift-today-card', [
            'data' => $extraShift ? self::build($user, $today, $extraShift) : null,
        ]);
    }

    /** Diekstrak static+public (2026-10-16) - dipakai juga oleh
     * ResolvesTodayAttendanceStatus::todayAttendanceStatus() buat gabung kartu Presensi Hari Ini
     * dengan info Extra/Double Shift jadi 1 kartu (khusus jadwal tipe shift), bukan 2 kartu
     * terpisah lagi - instruksi user. Widget ini sendiri tetap ada apa adanya (masih dites
     * terpisah), cuma tidak lagi dirender di Beranda. */
    public static function build($user, string $today, ExtraShiftAssignment $extraShift): array
    {
        $resolved = app(ScheduleResolver::class)->resolveForUserDate($user, $today);
        $normalConfig = ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false))
            ? $resolved['config']
            : null;

        $isDoubleShift = $extraShift->category === ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT;

        // "Ditugaskan oleh ... pada ..." cuma relevan buat source='assigned' (atasan input
        // langsung lewat halaman Extra Shift) - double_shift (akibat tukar shift, source
        // 'swap_conflict') created_by-nya cuma approver terakhir alur approval, bukan yang
        // "menugaskan" dalam arti yang dimaksud user, jadi sengaja tidak ditampilkan di situ.
        $assignedBy = $extraShift->source === 'assigned'
            ? ['name' => $extraShift->createdBy?->name, 'at' => $extraShift->created_at]
            : null;

        if (! $normalConfig) {
            // Hari itu libur/kosong (bukan Oncall - sudah difilter di query) - extra shift-nya
            // berdiri sendiri, tidak ada yang perlu dipecah.
            return [
                'is_double_shift' => $isDoubleShift,
                'assigned_by' => $assignedBy,
                'mode' => 'extra_only',
                'shift' => $extraShift->shiftType,
            ];
        }

        $shiftReader = app(ShiftAttendanceReader::class);
        $isAdjacent  = $shiftReader->isAdjacent($normalConfig, $extraShift);

        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;
        $earlierConfig  = $extraIsEarlier ? $extraShift->shiftType : $normalConfig;
        $laterConfig    = $extraIsEarlier ? $normalConfig : $extraShift->shiftType;

        return [
            'is_double_shift' => $isDoubleShift,
            'assigned_by' => $assignedBy,
            'mode' => $isAdjacent ? 'adjacent' : 'skip',
            'earlier' => $earlierConfig,
            'later' => $laterConfig,
        ];
    }
}
