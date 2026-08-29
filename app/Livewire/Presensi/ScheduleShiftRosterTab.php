<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Exports\Presensi\ScheduleShiftRosterExport;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use App\Models\Shift\ScheduleShiftRotation;
use App\Models\Shift\ShiftSwapRequests;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Tab "Detail Penjadwalan" di ScheduleDetailPage — roster harian per periode (bulan), tabel
 * karyawan x tanggal. Edit manual per sel (mode editMode/draft/saveRoster) kebuka buat SEMUA
 * jadwal, baik yang pakai Template maupun tidak — jadwal Template TETAP bisa "Generate dari
 * Template" (insertOrIgnore lewat firstOrCreate, tidak pernah menimpa baris yang sudah ada,
 * lihat planning/2026-07-18-restrukturisasi-jadwal-schedules.md §2.11), dan sesudahnya boleh
 * dikoreksi manual per sel juga lewat mode edit yang sama, bukan lagi dikunci read-only.
 * (Sebelumnya jadwal Template dikunci total, rencana awalnya koreksi lewat alur Pengajuan Tukar
 * Shift — diputuskan dibuka bebas saja, bukan dibangun jalur approval terpisah.)
 */
class ScheduleShiftRosterTab extends Component
{
    use WithFileUploads;

    public Schedule $schedule;

    public ?UploadedFile $importFile = null;

    /** Format Y-m, default bulan berjalan. */
    public string $period = '';

    /** Tanggal yang jadi patokan mulai rotasi pas generate — BUKAN disimpan permanen, dipilih
     * ulang tiap kali generate (lihat catatan di ScheduleShiftRotation). Default awal periode. */
    public string $rotationStartDate = '';

    /** Posisi hari di TEMPLATE yang berlaku PAS di $rotationStartDate — mis. rotasi baru mau
     * dimulai dari hari ke-3 pola (bukan selalu dari hari ke-1). Default 1. */
    public int $rotationStartDay = 1;

    /** Sel di grid CUMA bisa diedit setelah tombol "Mulai Isi Jadwal" diklik (editMode=true) —
     * bukan lagi langsung nyimpen tiap ganti sel. Perubahan ditampung di $draft dulu, baru
     * ditulis ke DB sekaligus pas tombol "Simpan" diklik (saveRoster()). Cuma relevan buat
     * jadwal yang TIDAK pakai Template (lihat setCell/generateFromTemplate). */
    public bool $editMode = false;

    /** @var array<string, array<string, string>> draft [user_id => [date => shift_type_id/'off'/'']] selama editMode. */
    public array $draft = [];

    public bool $showLogs = false;

    public function toggleLogs(): void
    {
        $this->showLogs = ! $this->showLogs;
    }

    public function mount(Schedule $schedule): void
    {
        // Sama persis gerbang ScheduleDetailPage (parent, satu-satunya tempat komponen ini
        // dirender) - dulu SISTEM_ABSENSI di sini, tapi itu permission BEDA yang karyawan biasa
        // (mis. perawat) juga sering tidak punya, jadi nested check ini malah nolak user yang
        // sudah lolos gerbang parent. Reuse SchedulePolicy::view() saja (2026-08-15).
        abort_unless(Auth::user()->can('view', $schedule), 403);

        $this->schedule = $schedule;
        $this->period = now()->format('Y-m');
        $this->rotationStartDate = now()->startOfMonth()->toDateString();
    }

    public function getCanEditJadwalProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value);
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

    /** Sel roster yang KENA pengajuan tukar shift (in_review / approved) - dipakai blade buat naruh
     * ikon "tukar" emas di pojok sel. Key: "{user_id}|{Y-m-d}". Scope: pengajuan yang req-schedule-nya
     * milik jadwal ini, dalam rentang bulan periode terpilih.
     *
     * @return array<string, true>
     */
    public function getSwapCellsProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        $swaps = ShiftSwapRequests::whereIn('status', ['in_review', 'approved'])
            ->whereHas('reqSchedule.shiftType', fn ($q) => $q->where('schedule_id', $this->schedule->id))
            ->get(['requester_id', 'req_date', 'target_id', 'target_date']);

        $cells = [];

        foreach ($swaps as $s) {
            $reqDate = $s->req_date->toDateString();
            if ($reqDate >= $start && $reqDate <= $end) {
                $cells[$s->requester_id . '|' . $reqDate] = true;
            }

            $targetDate = $s->target_date?->toDateString();
            if ($s->target_id && $targetDate && $targetDate >= $start && $targetDate <= $end) {
                $cells[$s->target_id . '|' . $targetDate] = true;
            }
        }

        return $cells;
    }

    /** Kalimat keterangan 1 baris log, bukan nampilin old/new/source mentah - "kosong -> terisi"
     * dibunyikan sebagai "Mengisi jadwal ...", bukan cuma "null -> P". */
    public function describeRosterLog(ScheduleShiftRosterLog $log): string
    {
        $label = function (?string $value): string {
            if ($value === null) {
                return 'kosong';
            }

            if ($value === 'off') {
                return 'Libur';
            }

            return $this->schedule->shiftTypes->firstWhere('id', $value)?->code ?? '-';
        };

        $name = $log->user?->name;
        $date = $log->date->translatedFormat('d M Y');

        return match (true) {
            $log->source === 'generate' => "Generate mengisi jadwal {$name} tanggal {$date}: {$label($log->new_value)}.",
            $log->source === 'reset' => "Reset menghapus jadwal {$name} tanggal {$date} (sebelumnya {$label($log->old_value)}).",
            $log->old_value === null => "Mengisi jadwal {$name} tanggal {$date}: {$label($log->new_value)}.",
            $log->new_value === null => "Mengosongkan jadwal {$name} tanggal {$date} (sebelumnya {$label($log->old_value)}).",
            default => "Mengubah jadwal {$name} tanggal {$date}: {$label($log->old_value)} \u{2192} {$label($log->new_value)}.",
        };
    }

    /** Riwayat perubahan roster periode ini - source manual/generate/reset, terbaru dulu. */
    public function getRosterLogsProperty()
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        return ScheduleShiftRosterLog::where('schedule_id', $this->schedule->id)
            ->whereBetween('date', [$start, $end])
            ->with(['user', 'changedBy'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /** Masuk mode edit — salin roster tersimpan saat ini ke $draft (sel kosong default ''),
     * supaya select di grid punya nilai awal yang benar sebelum diubah user. */
    public function startEdit(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value), 403);

        $roster = $this->roster;

        $this->draft = collect($this->employees)->mapWithKeys(function ($employee) use ($roster) {
            $row = collect($this->dates)->mapWithKeys(fn ($date) => [
                $date->toDateString() => $roster[$employee->id][$date->toDateString()] ?? '',
            ])->all();

            return [$employee->id => $row];
        })->all();

        $this->editMode = true;
    }

    public function cancelEdit(): void
    {
        $this->editMode = false;
        $this->draft = [];
    }

    /** Tulis $draft ke DB sekaligus — cuma sel yang nilainya beda dari roster tersimpan (hindari
     * update percuma ke sel yang tidak diubah). Reuse setCell() per sel biar logic persist-nya
     * satu tempat (delete-kalau-kosong, dst tidak dobel). */
    public function saveRoster(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value), 403);

        if (! $this->editMode) {
            return;
        }

        $currentRoster = $this->roster;
        $swapCells = $this->swapCells;
        $changed = 0;
        $blockedBySwap = 0;

        foreach ($this->draft as $userId => $row) {
            foreach ($row as $date => $value) {
                $before = $currentRoster[$userId][$date] ?? '';

                if ($before === $value) {
                    continue;
                }

                // Sel yang jadwalnya terkait pengajuan tukar shift tidak boleh diubah dari sini -
                // gerbang di blade bisa dilewati lewat request yang di-craft, jadi dicek ulang.
                if (isset($swapCells[$userId . '|' . $date])) {
                    $blockedBySwap++;
                    continue;
                }

                $this->setCell($userId, $date, $value ?: null);
                $changed++;
            }
        }

        $this->editMode = false;
        $this->draft = [];

        Notification::make()
            ->title('Jadwal berhasil disimpan')
            ->body("{$changed} sel diubah." . ($blockedBySwap ? " {$blockedBySwap} sel dilewati karena terkait pengajuan tukar shift." : ''))
            ->success()
            ->send();
    }

    /** Edit manual 1 sel — berlaku buat jadwal Template maupun manual (lihat catatan class-level
     * di atas). Value 'off' disimpan eksplisit (off_day=true, shift_type_id=null), beda dari
     * kosong yang menghapus baris. Pakai whereDate() (bukan where('date', ...) polos) — kolom
     * `date` di-cast 'date' pada model tapi dibandingkan sebagai string mentah di query, jadi
     * match-nya tergantung driver DB (MySQL toleran, sebagian lain tidak) kalau pakai equality
     * biasa. $source dicatat ke schedule_shift_roster_logs - 'manual' dari saveRoster() (baik
     * hasil edit langsung maupun draft yang diisi dari import), atau dipanggil terpisah pas
     * dites langsung. */
    public function setCell(string $userId, string $date, ?string $value, string $source = 'manual'): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value), 403);

        $existing = ScheduleShiftAssignment::where('user_id', $userId)->whereDate('date', $date)->first();
        $before = $existing ? ($existing->off_day ? 'off' : $existing->shift_type_id) : null;

        if (! $value) {
            $existing?->delete();

            $this->logRosterChange($userId, $date, $before, null, $source);

            return;
        }

        $attributes = [
            'company_id' => $this->schedule->company_id,
            'shift_type_id' => $value === 'off' ? null : $value,
            'off_day' => $value === 'off',
            'created_by' => Auth::id(),
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            ScheduleShiftAssignment::create($attributes + ['user_id' => $userId, 'date' => $date]);
        }

        $this->logRosterChange($userId, $date, $before, $value, $source);
    }

    private function logRosterChange(string $userId, string $date, ?string $old, ?string $new, string $source): void
    {
        if ($old === $new) {
            return;
        }

        ScheduleShiftRosterLog::create([
            'schedule_id' => $this->schedule->id,
            'user_id' => $userId,
            'date' => $date,
            'old_value' => $old,
            'new_value' => $new,
            'source' => $source,
            'changed_by' => Auth::id(),
        ]);
    }

    /** Isi roster 1 periode dari rotasi tiap karyawan — insertOrIgnore lewat firstOrCreate,
     * tidak pernah menimpa baris yang sudah ada. */
    public function generateFromTemplate(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value), 403);

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
                    $this->logRosterChange($employee->id, $date->toDateString(), null, $rotationRow->off_day ? 'off' : $rotationRow->shift_type_id, 'generate');
                }
            }
        }

        Notification::make()
            ->title('Generate selesai')
            ->body("{$filled} tanggal terisi baru. Tanggal yang sudah punya roster (manual/koreksi) tidak ditimpa.")
            ->success()
            ->send();
    }

    /** Hapus semua baris roster (termasuk yang sudah dikoreksi manual) di periode terpilih,
     * biar bisa di-generate ulang dari awal. Berlaku buat jadwal Template maupun manual. */
    public function resetPeriod(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value), 403);

        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        // Baris yang jadwalnya terkait pengajuan tukar shift TIDAK ikut direset - dikeluarkan dari
        // daftar hapus (konsisten dengan sel yang dikunci di grid).
        $swapCells = $this->swapCells;

        $rows = ScheduleShiftAssignment::whereIn('user_id', $this->employees->pluck('id'))
            ->whereBetween('date', [$start, $end])
            ->get()
            ->reject(fn ($row) => isset($swapCells[$row->user_id . '|' . $row->date->toDateString()]));

        if ($rows->isNotEmpty()) {
            ScheduleShiftRosterLog::insert($rows->map(fn ($row) => [
                'id' => (string) Str::ulid(),
                'schedule_id' => $this->schedule->id,
                'user_id' => $row->user_id,
                'date' => $row->date,
                'old_value' => $row->off_day ? 'off' : $row->shift_type_id,
                'new_value' => null,
                'source' => 'reset',
                'changed_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        $deleted = ScheduleShiftAssignment::whereIn('id', $rows->pluck('id'))->delete();

        $this->editMode = false;
        $this->draft = [];

        Notification::make()
            ->title('Jadwal direset')
            ->body("{$deleted} baris roster {$this->periodLabel} dihapus.")
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

    /** Baca ulang file hasil exportExcel() yang sudah diedit user - baris/kolom grid HARUS sama
     * posisinya kayak export (baris 1-2 judul, baris 3 header, baris 4.. data; kolom tanggal
     * diasumsikan urutan 1..akhir bulan periode ini, bukan dibaca dari isi kolomnya). Employee
     * dicocokkan by nama (exact, case-insensitive), shift dicocokkan by kode. Sel kosong =
     * kosongkan, "L" = Libur, kode tidak dikenal dilewati (dilaporkan sebagai warning).
     *
     * TIDAK langsung ditulis ke DB - hasil parse ditampung ke $draft (sama seperti startEdit())
     * dan editMode diaktifkan, jadi user WAJIB klik "Simpan" buat commit, sama seperti alur edit
     * manual. Validasi (nama tidak match, kode shift tidak dikenal) dilaporkan di sini, saat
     * import, bukan ditunda sampai user klik Simpan. Berlaku buat jadwal Template maupun manual,
     * sama seperti edit manual per sel. */
    public function updatedImportFile(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value), 403);

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls']);

        $rows = Excel::toArray(null, $this->importFile->getRealPath())[0] ?? [];
        $dataRows = array_slice($rows, 3);

        $employeesByName = $this->employees->keyBy(fn ($employee) => mb_strtolower(trim($employee->name)));
        $shiftTypesByCode = $this->schedule->shiftTypes->keyBy(fn ($type) => mb_strtolower($type->code));
        $roster = $this->roster;

        // Seed draft dari roster tersimpan dulu (sama kayak startEdit()) - baris/kolom yang tidak
        // disentuh file import tetap mempertahankan nilai lama, bukan ketimpa kosong.
        $draft = collect($this->employees)->mapWithKeys(function ($employee) use ($roster) {
            $row = collect($this->dates)->mapWithKeys(fn ($date) => [
                $date->toDateString() => $roster[$employee->id][$date->toDateString()] ?? '',
            ])->all();

            return [$employee->id => $row];
        })->all();

        $unknownNames = [];
        $unknownCodes = [];
        $changed = 0;

        foreach ($dataRows as $row) {
            $name = trim((string) ($row[0] ?? ''));

            if ($name === '') {
                continue;
            }

            $employee = $employeesByName->get(mb_strtolower($name));

            if (! $employee) {
                $unknownNames[$name] = true;

                continue;
            }

            foreach ($this->dates as $i => $date) {
                $code = trim((string) ($row[$i + 1] ?? ''));

                $value = match (true) {
                    $code === '' => '',
                    mb_strtolower($code) === 'l' => 'off',
                    default => $shiftTypesByCode->get(mb_strtolower($code))?->id,
                };

                if ($value === null) {
                    // Kode tidak dikenal (typo dsb) - lewati sel ini, jangan dianggap "kosongkan".
                    $unknownCodes[$code] = true;

                    continue;
                }

                if ($draft[$employee->id][$date->toDateString()] !== $value) {
                    $changed++;
                }

                $draft[$employee->id][$date->toDateString()] = $value;
            }
        }

        $this->draft = $draft;
        $this->editMode = true;
        $this->importFile = null;

        $warnings = [];

        if ($unknownNames) {
            $warnings[] = 'Nama tidak dikenal: '.implode(', ', array_keys($unknownNames));
        }

        if ($unknownCodes) {
            $warnings[] = 'Kode shift tidak dikenal: '.implode(', ', array_keys($unknownCodes));
        }

        Notification::make()
            ->title($warnings ? 'Import selesai dengan peringatan' : 'Import selesai')
            ->body("{$changed} sel akan berubah - periksa grid, lalu klik \"Simpan\" buat menyimpan.".($warnings ? ' '.implode(' | ', $warnings) : ''))
            ->when((bool) $warnings, fn ($n) => $n->warning(), fn ($n) => $n->success())
            ->send();
    }

    public function render()
    {
        return view('livewire.presensi.schedule-shift-roster-tab');
    }
}
