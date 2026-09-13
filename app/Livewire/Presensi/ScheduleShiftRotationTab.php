<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftRotation;
use App\Models\Shift\ScheduleShiftTeamMember;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Tab "Template Shift" di ScheduleDetailPage — komponen nested terpisah (bukan trait/partial
 * statis kayak tab Jam Kerja/Pengguna) karena state/aksinya CRUD sendiri. Aktivasi
 * `use_template` diatur DI SINI (tombol Aktifkan/Batalkan) — bukan di form Create/Edit Jadwal.
 *
 * Kalau aktif: 1 tabel grid — baris = karyawan yang sudah di-assign ke jadwal ini, kolom = posisi
 * hari dalam siklus rotasi (Hari 1, Hari 2, dst, bisa ditambah/dikurangi lewat tombol, SAMA buat
 * semua karyawan — bukan per-orang beda panjang siklus, biar 1 tabel gampang dibaca), tiap sel
 * 1 dropdown (kode shift ATAU "Libur", bukan checkbox terpisah). TIDAK ada tanggal mulai/anchor
 * di sini — ini murni pola, tanggal mulainya ditentukan pas generate (tab Detail Penjadwalan).
 * TIDAK ada entity "Template" terpisah — lihat App\Models\Shift\ScheduleShiftRotation.
 */
class ScheduleShiftRotationTab extends Component
{
    public Schedule $schedule;

    /** @var array<string, array<int, string>> keyed user_id => [day => 'off' atau shift_type_id] */
    public array $rows = [];

    /** Jumlah kolom Hari yang lagi ditampilkan — sama buat semua baris. */
    public int $dayCount = 1;

    public function mount(Schedule $schedule): void
    {
        // Tab Template Shift tidak punya mode "lihat saja" - buka = boleh edit. Satu permission.
        abort_unless(Auth::user()->can('Update:ShiftTemplates'), 403);

        $this->schedule = $schedule;
        $this->loadRows();
    }

    private function loadRows(): void
    {
        $employees = $this->employees;
        $existing = ScheduleShiftRotation::where('schedule_id', $this->schedule->id)->get()->groupBy('user_id');

        $this->dayCount = max(1, $existing->max(fn ($rows) => $rows->count()) ?? 1);

        $this->rows = [];

        foreach ($employees as $employee) {
            $userRotations = $existing->get($employee->id, collect())->keyBy('day');

            $days = [];
            for ($d = 1; $d <= $this->dayCount; $d++) {
                $r = $userRotations->get($d);
                $days[$d] = $r?->off_day ? 'off' : ($r?->shift_type_id ?? '');
            }

            $this->rows[$employee->id] = $days;
        }
    }

    /** Karyawan yang sudah di-assign ke jadwal shift ini (sumber sama dengan tabel "Karyawan
     * Terpilih" di form Create/Edit). */
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

    /** @return array<string, ?array<int, string>> user_id => shift_type_id yang boleh dipilih di
     * Template Rotasi - null = tidak dibatasi. Regu-nya diambil dari BULAN BERJALAN (now()), bukan
     * periode manapun - Template Rotasi murni pola general (tidak per-bulan), instruksi user
     * 2026-09-13 sengaja pilih patokan sederhana ini walau anggota regu bisa beda bulan depan. */
    public function getEmployeeAllowedShiftTypesProperty(): array
    {
        if (! $this->schedule->use_teams) {
            return [];
        }

        $period = now()->format('Y-m');

        $out = [];
        foreach ($this->employees as $employee) {
            $out[$employee->id] = ScheduleShiftTeamMember::allowedShiftTypeIdsFor($this->schedule, $employee->id, $period);
        }

        return $out;
    }

    public function activateTemplate(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftTemplates'), 403);

        $this->schedule->update(['use_template' => true]);
        $this->schedule->refresh();
        $this->loadRows();

        Notification::make()->title('Template Rotasi diaktifkan')->success()->send();
    }

    public function deactivateTemplate(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftTemplates'), 403);

        $this->schedule->update(['use_template' => false]);
        $this->schedule->refresh();

        Notification::make()->title('Template Rotasi dibatalkan')->body('Data rotasi yang sudah diisi tetap tersimpan kalau mau diaktifkan lagi nanti.')->success()->send();
    }

    public function addDayColumn(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftTemplates'), 403);

        $this->dayCount++;

        foreach ($this->rows as $userId => $row) {
            $this->rows[$userId][$this->dayCount] = '';
        }
    }

    public function removeDayColumn(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftTemplates'), 403);

        if ($this->dayCount <= 1) {
            return;
        }

        foreach ($this->rows as $userId => $row) {
            unset($this->rows[$userId][$this->dayCount]);
        }

        $this->dayCount--;
    }

    public function saveRotation(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftTemplates'), 403);

        $allowedByUser = $this->employeeAllowedShiftTypes;
        $blockedByRegu = 0;

        DB::transaction(function () use ($allowedByUser, &$blockedByRegu) {
            foreach ($this->rows as $userId => $row) {
                $keepIds = [];
                $allowedIds = $allowedByUser[$userId] ?? null;

                foreach ($row as $dayNumber => $value) {
                    // Shift bukan jatah Regu karyawan ini (bulan berjalan) - dilewati, dianggap
                    // kosong (bukan Libur) di sel itu, biar tidak diam-diam nyimpen pilihan lama.
                    if ($value && $value !== 'off' && $allowedIds !== null && ! in_array($value, $allowedIds, true)) {
                        $blockedByRegu++;
                        $value = '';
                    }

                    $rotation = ScheduleShiftRotation::updateOrCreate(
                        ['schedule_id' => $this->schedule->id, 'user_id' => $userId, 'day' => $dayNumber],
                        [
                            'shift_type_id' => $value === 'off' || $value === '' ? null : $value,
                            'off_day' => $value === 'off',
                            'created_by' => Auth::id(),
                        ]
                    );

                    $keepIds[] = $rotation->id;
                }

                ScheduleShiftRotation::where('schedule_id', $this->schedule->id)
                    ->where('user_id', $userId)
                    ->whereNotIn('id', $keepIds)
                    ->delete();
            }
        });

        $this->loadRows();

        Notification::make()
            ->title('Template Rotasi berhasil disimpan')
            ->body($blockedByRegu ? "{$blockedByRegu} sel dilewati karena shift tidak termasuk daftar Tim karyawan." : null)
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.presensi.schedule-shift-rotation-tab');
    }
}
