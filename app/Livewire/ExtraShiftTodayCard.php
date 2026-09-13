<?php

namespace App\Livewire;

use App\Enum\ShiftType;
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
 * Oncall dikecualikan total - begitu approved dia jadi shift NORMAL biasa (roster ke-timpa
 * lewat ExtraShiftAssignment::syncOncallRoster()), bukan lagi "extra" yang butuh reminder scan
 * tambahan - sudah tercover kartu Presensi Hari Ini biasa.
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
            ->whereHas('shiftType', fn ($q) => $q->where('shift_type', '!=', ShiftType::ONCALL->value))
            ->with('shiftType')
            ->first();

        return view('livewire.extra-shift-today-card', [
            'data' => $extraShift ? $this->buildData($user, $today, $extraShift) : null,
        ]);
    }

    private function buildData($user, string $today, ExtraShiftAssignment $extraShift): array
    {
        $resolved = app(ScheduleResolver::class)->resolveForUserDate($user, $today);
        $normalConfig = ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false))
            ? $resolved['config']
            : null;

        $isDoubleShift = $extraShift->category === ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT;

        if (! $normalConfig) {
            // Hari itu libur/kosong (bukan Oncall - sudah difilter di query) - extra shift-nya
            // berdiri sendiri, tidak ada yang perlu dipecah.
            return [
                'is_double_shift' => $isDoubleShift,
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
            'mode' => $isAdjacent ? 'adjacent' : 'skip',
            'earlier' => $earlierConfig,
            'later' => $laterConfig,
        ];
    }
}
