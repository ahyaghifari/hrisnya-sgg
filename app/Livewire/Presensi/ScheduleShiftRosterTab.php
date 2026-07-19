<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Exports\Presensi\ScheduleShiftRosterExport;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRotation;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Tab "Detail Penjadwalan" di ScheduleDetailPage — roster harian per periode (bulan), tabel
 * karyawan x tanggal. Edit manual per sel SELALU boleh menimpa (koreksi admin). Tombol
 * "Generate dari Template" cuma muncul kalau $schedule->use_template — insertOrIgnore lewat
 * firstOrCreate, TIDAK PERNAH menimpa baris yang sudah ada (bisa hasil tukar shift/koreksi
 * manual), sesuai aturan planning/2026-07-18-restrukturisasi-jadwal-schedules.md §2.11.
 */
class ScheduleShiftRosterTab extends Component
{
    public Schedule $schedule;

    /** Format Y-m, default bulan berjalan. */
    public string $period = '';

    /** Tanggal yang jadi patokan mulai rotasi pas generate — BUKAN disimpan permanen, dipilih
     * ulang tiap kali generate (lihat catatan di ScheduleShiftRotation). Default awal periode. */
    public string $rotationStartDate = '';

    /** Posisi hari di TEMPLATE yang berlaku PAS di $rotationStartDate — mis. rotasi baru mau
     * dimulai dari hari ke-3 pola (bukan selalu dari hari ke-1). Default 1. */
    public int $rotationStartDay = 1;

    public function mount(Schedule $schedule): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        $this->schedule = $schedule;
        $this->period = now()->format('Y-m');
        $this->rotationStartDate = now()->startOfMonth()->toDateString();
    }

    public function updatedPeriod(): void
    {
        $this->rotationStartDate = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
    }

    public function getPeriodLabelProperty(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)->locale('id')->translatedFormat('F Y');
    }

    public function getEmployeesProperty()
    {
        return ScheduleAssignment::where('schedule_id', $this->schedule->id)
            ->whereNotNull('user_id')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy('name')
            ->values();
    }

    /** Panjang siklus template (sama buat semua karyawan, lihat ScheduleShiftRotationTab — 1
     * tabel grid, kolom Hari-nya seragam) — dipakai buat opsi dropdown "mulai dari hari ke-". */
    public function getCycleLengthProperty(): int
    {
        $firstEmployeeId = $this->employees->first()?->id;

        if (! $firstEmployeeId) {
            return 1;
        }

        return max(1, ScheduleShiftRotation::where('schedule_id', $this->schedule->id)
            ->where('user_id', $firstEmployeeId)
            ->count());
    }

    /** @return array<int, Carbon> tanggal 1..akhir bulan periode terpilih. */
    public function getDatesProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();

        return collect(range(0, $start->daysInMonth - 1))
            ->map(fn ($i) => $start->copy()->addDays($i))
            ->all();
    }

    /** @return array<string, array<string, string>> [user_id => [date => shift_type_id atau 'off']] */
    public function getRosterProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        $userIds = $this->employees->pluck('id')->all();

        $rows = ScheduleShiftAssignment::whereIn('user_id', $userIds)
            ->whereBetween('date', [$start, $end])
            ->get();

        $roster = [];

        foreach ($rows as $row) {
            $roster[$row->user_id][$row->date->toDateString()] = $row->off_day ? 'off' : $row->shift_type_id;
        }

        return $roster;
    }

    /** Edit manual 1 sel — SELALU boleh menimpa (koreksi admin), beda dari generate. Value 'off'
     * disimpan eksplisit (off_day=true, shift_type_id=null), beda dari kosong yang menghapus baris. */
    public function setCell(string $userId, string $date, ?string $value): void
    {
        if (! $value) {
            ScheduleShiftAssignment::where('user_id', $userId)->where('date', $date)->delete();

            return;
        }

        ScheduleShiftAssignment::updateOrCreate(
            ['user_id' => $userId, 'date' => $date],
            [
                'company_id' => $this->schedule->company_id,
                'shift_type_id' => $value === 'off' ? null : $value,
                'off_day' => $value === 'off',
                'created_by' => Auth::id(),
            ]
        );
    }

    /** Isi roster 1 periode dari rotasi tiap karyawan — insertOrIgnore lewat firstOrCreate,
     * tidak pernah menimpa baris yang sudah ada. */
    public function generateFromTemplate(): void
    {
        if (! $this->schedule->use_template) {
            return;
        }

        $this->validate([
            'rotationStartDate' => 'required|date',
            'rotationStartDay' => 'required|integer|min:1',
        ], [], ['rotationStartDate' => 'tanggal mulai rotasi', 'rotationStartDay' => 'hari ke- di template']);

        $anchor = Carbon::parse($this->rotationStartDate);
        $filled = 0;

        foreach ($this->employees as $employee) {
            foreach ($this->dates as $date) {
                // Rotasi cuma berlaku MULAI dari $anchor — tanggal sebelum itu (kalau periode
                // yang digenerate lebih awal dari tanggal mulai) sengaja dilewati, bukan
                // diekstrapolasi mundur.
                if ($date->lt($anchor)) {
                    continue;
                }

                $dayNumber = ScheduleShiftRotation::dayNumberFor($this->schedule->id, $employee->id, $anchor, $date, $this->rotationStartDay);

                if ($dayNumber === null) {
                    continue;
                }

                $rotationRow = ScheduleShiftRotation::where('schedule_id', $this->schedule->id)
                    ->where('user_id', $employee->id)
                    ->where('day', $dayNumber)
                    ->first();

                if (! $rotationRow || (! $rotationRow->off_day && ! $rotationRow->shift_type_id)) {
                    continue;
                }

                $assignment = ScheduleShiftAssignment::firstOrCreate(
                    ['user_id' => $employee->id, 'date' => $date->toDateString()],
                    [
                        'company_id' => $this->schedule->company_id,
                        'shift_type_id' => $rotationRow->shift_type_id,
                        'off_day' => $rotationRow->off_day,
                        'created_by' => Auth::id(),
                    ]
                );

                if ($assignment->wasRecentlyCreated) {
                    $filled++;
                }
            }
        }

        Notification::make()
            ->title('Generate selesai')
            ->body("{$filled} tanggal terisi baru. Tanggal yang sudah punya roster (manual/koreksi) tidak ditimpa.")
            ->success()
            ->send();
    }

    public function exportExcel()
    {
        return Excel::download(
            new ScheduleShiftRosterExport($this->schedule, $this->employees, $this->dates, $this->roster, $this->periodLabel),
            'detail-penjadwalan-'.str($this->schedule->name)->slug().'-'.$this->period.'.xlsx'
        );
    }

    public function render()
    {
        return view('livewire.presensi.schedule-shift-roster-tab');
    }
}
