<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Exports\Presensi\ScheduleShiftRosterExport;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use App\Models\Shift\ScheduleShiftRotation;
use App\Models\Shift\ScheduleShiftTeamMember;
use App\Models\Shift\ShiftSwapRequests;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /** Batas bawah periode yang bisa dibuka/diisi - instruksi user 2026-09-13, tidak ada jadwal
     * shift sebelum bulan ini yang perlu diakses lewat halaman ini. */
    private const MIN_PERIOD = '2026-09';

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

    /** Cara pembagian tim ditampilkan di grid — 'row' (default, baris pemisah colspan penuh
     * per tim, instruksi user 2026-09-12) atau 'column' (kolom rowspan di samping nama
     * karyawan, diminta user 2026-09-15 buat jadwal yang timnya sedikit/anggotanya banyak per
     * tim, biar tidak makan baris ekstra). Preferensi TAMPILAN saja (bukan data jadwal) - disimpan
     * ke session per browser (instruksi user 2026-09-15), bukan per-jadwal/per-user di DB, jadi
     * cukup diingat sepanjang browser ini dipakai, tidak perlu migrasi tabel baru. */
    public string $teamGroupMode = 'row';

    private const TEAM_GROUP_MODE_SESSION_KEY = 'roster.team_group_mode';

    public function setTeamGroupMode(string $mode): void
    {
        $this->teamGroupMode = $mode === 'column' ? 'column' : 'row';

        session()->put(self::TEAM_GROUP_MODE_SESSION_KEY, $this->teamGroupMode);
    }

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
        $this->period = max(now()->format('Y-m'), self::MIN_PERIOD);
        $this->rotationStartDate = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $this->teamGroupMode = session()->get(self::TEAM_GROUP_MODE_SESSION_KEY) === 'column' ? 'column' : 'row';
    }

    public function getCanEditJadwalProperty(): bool
    {
        return Auth::user()->can('Update:ShiftScheduleAssignments');
    }

    /** Bulan 2+ ke depan dari bulan berjalan ASLI (bukan periode manapun yang lagi dibuka)
     * dikunci dari Isi Jadwal/Generate/Import/Export - instruksi user 2026-09-16: jadwal cuma
     * boleh diisi buat bulan berjalan + bulan depan (bulan depan otomatis mewarisi Tim dari
     * bulan berjalan sebagai draf, lihat ScheduleShiftTeamTab::syncAssignForm() - carry-over
     * itu sudah ada duluan, sama persis threshold-nya di sini). Rolling per bulan asli, BUKAN
     * per periode yang lagi dibuka - begitu bulan berjalan maju ke Oktober, November otomatis
     * kebuka (dapat carry-over Tim dari Oktober) dan Desember kekunci. Grid tetap bisa DILIHAT
     * (read-only) sejauh apa pun - cuma aksi tulisnya yang dikunci. */
    public function getPeriodLockedProperty(): bool
    {
        return $this->period > now()->addMonth()->format('Y-m');
    }

    /** Permission "Edit Jadwal" (Update:Schedule, sama gerbang tombol Edit Jadwal di
     * ScheduleDetailPage) - pemilik/pengelola jadwal ini bebas ubah tanggal berapa pun termasuk
     * yang sudah lewat (mis. koreksi data lama). Yang cuma punya Update:ShiftScheduleAssignments
     * (mis. Kepala Ruangan) dibatasi mulai hari ini saja lewat semua jalur input (manual, Isi
     * Cepat, Generate, Import) - histori presensi yang sudah terjadi tidak boleh diubah
     * sembarangan dari sini. */
    public function getCanEditPastDatesProperty(): bool
    {
        return Auth::user()->can('update', $this->schedule);
    }

    /** 'Y-m-d' hari ini kalau user dibatasi, null kalau bebas (canEditPastDates) - dipakai
     * seragam di saveRoster/generateFromTemplate/resetPeriod buat nge-skip tanggal yang sudah
     * lewat. Perbandingan string 'Y-m-d' aman secara leksikografis. */
    private function lockBeforeDate(): ?string
    {
        return $this->canEditPastDates ? null : now()->startOfDay()->toDateString();
    }

    /** ScheduleShiftAssignment pakai trait BelongsToCompany (global scope company_id = company
     * user login, cuma tembus buat Super Admin) - TIDAK kenal permission lihat_perusahaan_lain
     * sama sekali. Akibatnya: user yang MEMANG diizinkan lihat company lain (lolos gerbang
     * SchedulePolicy::view() di mount() - Schedule sendiri TIDAK pakai BelongsToCompany) bisa
     * buka halaman jadwal company lain, tapi roster-nya keliatan kosong total karena baris
     * ScheduleShiftAssignment company itu kesaring diam-diam (dilaporkan user 2026-09-16, data
     * kebukti ADA lewat tinker - console skip scope-nya, makanya nembus di sana tapi tidak di
     * request web biasa). Scope dibuka cuma kalau jadwal ini MEMANG beda company DAN user
     * beneran punya izinnya - bukan dibuka buat semua orang. */
    private function shiftAssignmentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = ScheduleShiftAssignment::query();

        if ($this->schedule->company_id !== Auth::user()->company_id
            && Auth::user()->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_ABSENSI)) {
            $query->withoutGlobalScope('company');
        }

        return $query;
    }

    public function updatedPeriod(): void
    {
        if ($this->period < self::MIN_PERIOD) {
            $this->period = self::MIN_PERIOD;
        }

        $this->rotationStartDate = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
    }

    public function getPeriodLabelProperty(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)->locale('id')->translatedFormat('F Y');
    }

    public function getEmployeesProperty()
    {
        $employees = ScheduleAssignment::where('schedule_id', $this->schedule->id)
            ->whereNotNull('user_id')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        if (! $this->schedule->use_teams) {
            return $employees->sortBy('name')->values();
        }

        // Dikelompokkan per regu (tab "Regu", diatur per bulan - pakai $period yang lagi
        // dibuka di sini juga) - urut nama regu, lalu nama karyawan; yang belum punya regu
        // bulan ini nyempil paling akhir. Cuma urutan baris yang berubah, mekanisme drag-select
        // (rfRow = $loop->index di blade) tidak kesentuh sama sekali, tetap jalan seperti biasa.
        $teamNameByUser = ScheduleShiftTeamMember::where('schedule_id', $this->schedule->id)
            ->where('period', $this->period)
            ->with('team')
            ->get()
            ->keyBy('user_id')
            ->map(fn ($m) => $m->team?->name);

        return $employees
            ->sortBy(fn ($u) => ($teamNameByUser->get($u->id) ?? "\u{FFFF}") . '|' . $u->name)
            ->values();
    }

    /** @return array<string, array{name: string, color: ?string}>> user_id => regu bulan
     * $period ini (cuma yang PUNYA regu — dipakai buat baris pemisah nama regu di grid, lihat
     * blade). Kosong kalau Regu tidak aktif. */
    public function getEmployeeTeamInfoProperty(): array
    {
        if (! $this->schedule->use_teams) {
            return [];
        }

        return ScheduleShiftTeamMember::where('schedule_id', $this->schedule->id)
            ->where('period', $this->period)
            ->with('team.shiftTypes')
            ->get()
            ->filter(fn ($m) => $m->team)
            ->mapWithKeys(fn ($m) => [$m->user_id => [
                'name' => $m->team->name,
                'color' => $m->team->color,
                // null = regu ini TIDAK dibatasi (boleh semua shift), lihat
                // ScheduleShiftTeamMember::allowedShiftTypeIdsFor().
                'shift_type_ids' => $m->team->shiftTypes->pluck('id')->all() ?: null,
            ]])
            ->all();
    }

    /** Daftar Regu jadwal ini (bukan per periode - keanggotaan yang per-bulan, tapi daftar
     * Regu & jatah shift-nya melekat ke Schedule) - dipakai ganti "Keterangan" flat jadi badge
     * per Regu dengan hover isi shift jatahnya (instruksi user 2026-09-15), karyawan yang beda
     * Regu bisa beda jatah shift jadi legend lama (semua shiftTypes schedule tanpa konteks Regu)
     * menyesatkan begitu Regu aktif. */
    public function getTeamsProperty()
    {
        return \App\Models\Shift\ScheduleShiftTeam::where('schedule_id', $this->schedule->id)
            ->with('shiftTypes')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, int> nama tim (atau '__tanpa_tim__') => jumlah karyawan di grup itu -
     * dipakai buat rowspan kolom Tim pas $teamGroupMode = 'column'. Employees sudah diurut per
     * tim di getEmployeesProperty(), jadi grouping ini pasti menghasilkan blok yang berurutan. */
    public function getTeamGroupSizesProperty(): array
    {
        return $this->employees
            ->groupBy(fn ($u) => $this->employeeTeamInfo[$u->id]['name'] ?? '__tanpa_tim__')
            ->map->count()
            ->all();
    }

    /** Boleh diisi shift apa saja buat 1 user - null = tidak dibatasi (Regu tidak aktif, user
     * belum punya regu bulan ini, atau regunya belum diatur daftar shift-nya). Reuse
     * $employeeTeamInfo (sudah 1 query, bukan query baru) - lihat komentar model. */
    private function isShiftAllowedForUser(string $userId, string $shiftTypeId): bool
    {
        $allowedIds = $this->employeeTeamInfo[$userId]['shift_type_ids'] ?? null;

        return $allowedIds === null || in_array($shiftTypeId, $allowedIds, true);
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

    /** Jumlah hari sebelum tanggal 1 yang ditampilkan read-only sebagai konteks - biar pola shift
     * bisa "disambung" dari akhir bulan sebelumnya (dipilih sebagai sumber pola, tidak bisa diedit). */
    public int $leadDays = 7;

    /** @return array<int, Carbon> 7 hari sebelum tanggal 1 periode, urut lama->baru. */
    public function getLeadDatesProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();

        return collect(range($this->leadDays, 1))
            ->map(fn ($i) => $start->copy()->subDays($i))
            ->all();
    }

    /** @return array<string, array<string, string>> [user_id => [date => shift_type_id atau 'off']] */
    public function getRosterProperty(): array
    {
        // Mundur $leadDays biar kolom konteks sebelum tanggal 1 ikut terisi nilainya.
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->subDays($this->leadDays)->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        $userIds = $this->employees->pluck('id')->all();

        $rows = $this->shiftAssignmentQuery()->whereIn('user_id', $userIds)
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
     * Sengaja pakai user_id & date dari relasi reqSchedule/targetSchedule LANGSUNG (bukan kolom
     * requester_id/req_date/target_id/target_date di shift_swap_requests) - begitu approved,
     * executeApprovedSwap() mengubah user_id baris schedule_shift_assignments itu sendiri (mode
     * 'move'/'move_over_off_day'), jadi kolom requester_id/target_id jadi STALE (nunjuk pemilik
     * LAMA yang sudah pindah/kosong). Baris yang dihapus total (mode 'double_shift') otomatis
     * dilewati (relasinya null) - tidak ada sel schedule_shift_assignments buat ditandai lagi,
     * hasilnya sudah pindah jadi ExtraShiftAssignment.
     *
     * @return array<string, true>
     */
    public function getSwapCellsProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        // Tanggal dibatasi di QUERY (bukan cuma di-filter di PHP setelah tarik semua histori
        // swap sepanjang masa) - dulu ambil SEMUA in_review/approved sejak jadwal ini ada, makin
        // lama makin berat kena tarik ulang tiap render (dikeluhkan user "berat" 2026-09-15).
        $swaps = ShiftSwapRequests::whereIn('status', ['in_review', 'approved'])
            ->whereHas('reqSchedule.shiftType', fn ($q) => $q->where('schedule_id', $this->schedule->id))
            ->where(function ($q) use ($start, $end) {
                $q->whereHas('reqSchedule', fn ($q2) => $q2->whereBetween('date', [$start, $end]))
                    ->orWhereHas('targetSchedule', fn ($q2) => $q2->whereBetween('date', [$start, $end]));
            })
            ->with(['reqSchedule:id,user_id,date', 'targetSchedule:id,user_id,date'])
            ->get();

        $cells = [];

        foreach ($swaps as $s) {
            foreach ([$s->reqSchedule, $s->targetSchedule] as $slot) {
                if (! $slot) {
                    continue;
                }

                $date = $slot->date->toDateString();
                if ($date >= $start && $date <= $end) {
                    $cells[$slot->user_id . '|' . $date] = true;
                }
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
            // Cuti/Izin Tidak Masuk disetujui - lihat AttendaceReqService::syncApprovedAbsenceToRoster().
            $log->source === 'leave_approved' => "Otomatis Libur {$name} tanggal {$date} (Cuti/Izin disetujui, sebelumnya {$label($log->old_value)}).",
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
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);
        abort_if($this->periodLocked, 403);

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

    /** Tulis $draft ke DB SEKALIGUS lewat batch (upsert + delete + insert log), BUKAN loop
     * setCell() per sel (dulu 1 SELECT + 1 write + 1 log INSERT per sel - grid 30 karyawan x 30
     * hari yang banyak berubah gampang tembus >1000 query dalam 1 klik Simpan, lambat sekali -
     * dikeluhkan user 2026-09-14). setCell() TETAP ada apa adanya buat edit 1 sel langsung
     * (dipanggil test & alur lain) - gerbang/aturannya (swap-lock, past-date-lock, jatah Tim)
     * disalin di sini supaya hasil akhirnya identik, cuma eksekusi DB-nya dibatch lewat
     * persistRosterBatch(). */
    public function saveRoster(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);
        abort_if($this->periodLocked, 403);

        if (! $this->editMode) {
            return;
        }

        $currentRoster = $this->roster;
        $swapCells = $this->swapCells;
        $lockBeforeDate = $this->lockBeforeDate();
        $blockedBySwap = 0;
        $blockedByPastDate = 0;
        $blockedByRegu = 0;

        $pending = [];

        foreach ($this->draft as $userId => $row) {
            foreach ($row as $date => $value) {
                $before = $currentRoster[$userId][$date] ?? '';
                $value = $value ?: null;

                if ($before === ($value ?? '')) {
                    continue;
                }

                // Sel yang jadwalnya terkait pengajuan tukar shift tidak boleh diubah dari sini -
                // gerbang di blade bisa dilewati lewat request yang di-craft, jadi dicek ulang.
                if (isset($swapCells[$userId . '|' . $date])) {
                    $blockedBySwap++;
                    continue;
                }

                // Yang cuma punya Update:ShiftScheduleAssignments (bukan Edit Jadwal) dibatasi
                // mulai hari ini - perbandingan string 'Y-m-d' aman leksikografis.
                if ($lockBeforeDate && $date < $lockBeforeDate) {
                    $blockedByPastDate++;
                    continue;
                }

                if ($value && $value !== 'off' && ! $this->isShiftAllowedForUser($userId, $value)) {
                    $blockedByRegu++;
                    continue;
                }

                $pending[] = ['user_id' => $userId, 'date' => $date, 'before' => $before ?: null, 'value' => $value];
            }
        }

        $changed = count($pending);

        if ($pending) {
            $this->persistRosterBatch($pending);
        }

        $this->editMode = false;
        $this->draft = [];

        // Buang memoisasi computed — kalau tidak, render() di request yang sama masih baca
        // roster/log versi cache SEBELUM tulisan setCell(), grid & Riwayat baru muncul pas refresh.
        unset($this->roster, $this->rosterLogs);

        Notification::make()
            ->title('Jadwal berhasil disimpan')
            ->body(
                "{$changed} sel diubah."
                . ($blockedBySwap ? " {$blockedBySwap} sel dilewati karena terkait pengajuan tukar shift." : '')
                . ($blockedByPastDate ? " {$blockedByPastDate} sel dilewati karena tanggalnya sudah lewat." : '')
                . ($blockedByRegu ? " {$blockedByRegu} sel dilewati karena shift tidak termasuk daftar Tim karyawan." : '')
            )
            ->success()
            ->send();
    }

    /**
     * Tulis semua sel yang lolos gerbang saveRoster() sekaligus, cuma segelintir query total
     * (bukan per sel): 1 SELECT cari baris existing (buat tahu delete vs upsert & id yang mau
     * dihapus), 1 DELETE (sel yang dikosongkan), 1 upsert (sel yang diisi/diubah - insert baris
     * baru ATAU update yang sudah ada lewat unique (user_id,date), 1 tabel sekaligus), 1 INSERT
     * log riwayat. Semantik akhirnya identik dengan setCell() dipanggil satu-satu (delete kalau
     * kosong, off_day=true buat 'off', log old->new per sel), cuma jalurnya dibatch.
     *
     * @param array<int, array{user_id:string, date:string, before:?string, value:?string}> $pending
     */
    private function persistRosterBatch(array $pending): void
    {
        $userIds = array_unique(array_column($pending, 'user_id'));
        $dates = array_unique(array_column($pending, 'date'));

        // Cari id baris existing buat (user_id,date) yang TERLIBAT saja - dipakai nentuin delete
        // pakai id asli (portabel lintas driver DB). whereBetween (bukan whereIn/equality
        // biasa) - sama pola dengan getRosterProperty(), aman lintas driver (lihat catatan
        // whereDate() di docblock setCell()).
        $existingIds = $this->shiftAssignmentQuery()->whereIn('user_id', $userIds)
            ->whereBetween('date', [min($dates), max($dates)])
            ->get(['id', 'user_id', 'date'])
            ->keyBy(fn ($row) => $row->user_id . '|' . $row->date->toDateString())
            ->map(fn ($row) => $row->id);

        $now = now();
        $companyId = $this->schedule->company_id;
        $actorId = Auth::id();

        $deleteIds = [];
        $upsertRows = [];
        $logRows = [];

        foreach ($pending as $cell) {
            $key = $cell['user_id'] . '|' . $cell['date'];
            $existingId = $existingIds[$key] ?? null;

            if (! $cell['value']) {
                if ($existingId) {
                    $deleteIds[] = $existingId;
                }
            } else {
                $upsertRows[] = [
                    'id' => $existingId ?: (string) Str::ulid(),
                    'user_id' => $cell['user_id'],
                    'date' => $cell['date'],
                    'company_id' => $companyId,
                    'shift_type_id' => $cell['value'] === 'off' ? null : $cell['value'],
                    'off_day' => $cell['value'] === 'off',
                    'created_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $logRows[] = [
                'id' => (string) Str::ulid(),
                'schedule_id' => $this->schedule->id,
                'user_id' => $cell['user_id'],
                'date' => $cell['date'],
                'old_value' => $cell['before'],
                'new_value' => $cell['value'],
                'source' => 'manual',
                'changed_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($deleteIds, $upsertRows, $logRows) {
            if ($deleteIds) {
                $this->shiftAssignmentQuery()->whereIn('id', $deleteIds)->delete();
            }

            if ($upsertRows) {
                ScheduleShiftAssignment::upsert(
                    $upsertRows,
                    ['user_id', 'date'],
                    ['company_id', 'shift_type_id', 'off_day', 'created_by', 'updated_at']
                );
            }

            if ($logRows) {
                ScheduleShiftRosterLog::insert($logRows);
            }
        });
    }

    /** Edit manual 1 sel — berlaku buat jadwal Template maupun manual (lihat catatan class-level
     * di atas). Value 'off' disimpan eksplisit (off_day=true, shift_type_id=null), beda dari
     * kosong yang menghapus baris. Pakai whereDate() (bukan where('date', ...) polos) — kolom
     * `date` di-cast 'date' pada model tapi dibandingkan sebagai string mentah di query, jadi
     * match-nya tergantung driver DB (MySQL toleran, sebagian lain tidak) kalau pakai equality
     * biasa. $source dicatat ke schedule_shift_roster_logs - 'manual' dari saveRoster() (baik
     * hasil edit langsung maupun draft yang diisi dari import), atau dipanggil terpisah pas
     * dites langsung.
     *
     * @return bool false = sel DILEWATI (shift-nya bukan jatah regu user ini, lihat
     *   isShiftAllowedForUser()), tidak ada apa pun ditulis/dilog. */
    public function setCell(string $userId, string $date, ?string $value, string $source = 'manual'): bool
    {
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);
        // Dicek dari BULAN $date sendiri, bukan $this->period - setCell() dipanggil lepas dari
        // periode yang lagi dibuka (lihat docblock di atas persistRosterBatch()).
        abort_if(substr($date, 0, 7) > now()->addMonth()->format('Y-m'), 403);

        if ($value && $value !== 'off' && ! $this->isShiftAllowedForUser($userId, $value)) {
            return false;
        }

        $existing = $this->shiftAssignmentQuery()->where('user_id', $userId)->whereDate('date', $date)->first();
        $before = $existing ? ($existing->off_day ? 'off' : $existing->shift_type_id) : null;

        if (! $value) {
            $existing?->delete();

            $this->logRosterChange($userId, $date, $before, null, $source);

            return true;
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

        return true;
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

    /** Isi roster 1 periode dari rotasi tiap karyawan — tidak pernah menimpa baris yang sudah
     * ada. Dibatch (1 query rotasi semua karyawan, 1 query existing assignments, 1 insert, 1 log
     * insert) - versi lama panggil ScheduleShiftRotation::dayNumberFor() (ada count() query di
     * dalamnya) + 1 SELECT rotasi + firstOrCreate (SELECT+INSERT) PER SEL, jadi ribuan query buat
     * roster besar (mis. 50 karyawan x 30 hari = ribuan query, dikeluhkan user "berat" 2026-09-15). */
    public function generateFromTemplate(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);
        abort_if($this->periodLocked, 403);

        if (! $this->schedule->use_template) {
            return;
        }

        $this->validate([
            'rotationStartDate' => 'required|date',
            'rotationStartDay' => 'required|integer|min:1',
        ], [], ['rotationStartDate' => 'tanggal mulai rotasi', 'rotationStartDay' => 'hari ke- di template']);

        $anchor = Carbon::parse($this->rotationStartDate);
        $lockBeforeDate = $this->lockBeforeDate();
        $employeeIds = $this->employees->pluck('id')->all();
        $dates = $this->dates;
        $dateStrings = collect($dates)->map->toDateString();

        // Semua rotasi karyawan-karyawan ini, dikelompokkan user_id => [day => row] - ganti 1
        // SELECT per sel jadi 1 query total. Panjang siklus tiap user dari jumlah baris rotasinya
        // sendiri (sama seperti dayNumberFor() dulu hitung, cuma sekali per karyawan bukan per sel).
        $rotationsByUser = ScheduleShiftRotation::where('schedule_id', $this->schedule->id)
            ->whereIn('user_id', $employeeIds)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('day'));

        // Sel yang SUDAH ada roster-nya di periode ini - dilewati (tidak ditimpa), dicek dari
        // 1 query di awal (bukan firstOrCreate SELECT per sel).
        $existingKeys = $this->shiftAssignmentQuery()->whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$dateStrings->min(), $dateStrings->max()])
            ->get(['user_id', 'date'])
            ->map(fn ($row) => $row->user_id . '|' . $row->date->toDateString())
            ->flip();

        $now = now();
        $companyId = $this->schedule->company_id;
        $actorId = Auth::id();
        $insertRows = [];
        $logRows = [];
        $filled = 0;

        foreach ($this->employees as $employee) {
            $rotationByDay = $rotationsByUser->get($employee->id);
            $cycleLength = $rotationByDay?->count() ?? 0;

            if ($cycleLength === 0) {
                continue;
            }

            foreach ($dates as $date) {
                // Rotasi cuma berlaku MULAI dari $anchor — tanggal sebelum itu (kalau periode
                // yang digenerate lebih awal dari tanggal mulai) sengaja dilewati, bukan
                // diekstrapolasi mundur.
                if ($date->lt($anchor)) {
                    continue;
                }

                // Yang cuma punya Update:ShiftScheduleAssignments (bukan Edit Jadwal) dibatasi
                // mulai hari ini - sama kayak saveRoster()/resetPeriod().
                if ($lockBeforeDate && $date->toDateString() < $lockBeforeDate) {
                    continue;
                }

                $dateStr = $date->toDateString();

                if (isset($existingKeys[$employee->id . '|' . $dateStr])) {
                    continue;
                }

                $diff = $anchor->diffInDays($date, false) + ($this->rotationStartDay - 1);
                $dayNumber = (($diff % $cycleLength) + $cycleLength) % $cycleLength + 1;

                $rotationRow = $rotationByDay->get($dayNumber);

                if (! $rotationRow || (! $rotationRow->off_day && ! $rotationRow->shift_type_id)) {
                    continue;
                }

                // Shift hasil rotasi bukan jatah Regu karyawan ini bulan itu - dilewati, TIDAK
                // ditulis sama sekali (bukan ditimpa off/kosong), sama pola dengan "belum diisi".
                if ($rotationRow->shift_type_id && ! $this->isShiftAllowedForUser($employee->id, $rotationRow->shift_type_id)) {
                    continue;
                }

                $insertRows[] = [
                    'id' => (string) Str::ulid(),
                    'user_id' => $employee->id,
                    'date' => $dateStr,
                    'company_id' => $companyId,
                    'shift_type_id' => $rotationRow->shift_type_id,
                    'off_day' => $rotationRow->off_day,
                    'created_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $logRows[] = [
                    'id' => (string) Str::ulid(),
                    'schedule_id' => $this->schedule->id,
                    'user_id' => $employee->id,
                    'date' => $dateStr,
                    'old_value' => null,
                    'new_value' => $rotationRow->off_day ? 'off' : $rotationRow->shift_type_id,
                    'source' => 'generate',
                    'changed_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $filled++;
            }
        }

        if ($insertRows) {
            DB::transaction(function () use ($insertRows, $logRows) {
                ScheduleShiftAssignment::insert($insertRows);
                ScheduleShiftRosterLog::insert($logRows);
            });
        }

        unset($this->roster, $this->rosterLogs);

        Notification::make()
            ->title('Generate selesai')
            ->body("{$filled} tanggal terisi baru. Tanggal yang sudah punya roster (manual/koreksi) tidak ditimpa.")
            ->success()
            ->send();
    }

    /** Hapus baris roster (termasuk yang sudah dikoreksi manual) di periode terpilih. Yang cuma
     * punya Update:ShiftScheduleAssignments (bukan Edit Jadwal) dibatasi mulai HARI INI saja -
     * tanggal yang sudah lewat sengaja tidak disentuh (histori presensi yang sudah terjadi tidak
     * boleh ikut ke-reset). Yang punya Edit Jadwal (canEditPastDates) bebas dari awal bulan. Kalau
     * periode terpilih sudah lewat semua (bulan lampau) buat user yang dibatasi, start jadi > end
     * -> tidak ada baris yang kena, sesuai maksud yang sama. Berlaku buat jadwal Template maupun
     * manual. */
    public function resetPeriod(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);

        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();
        if ($lockBeforeDate = $this->lockBeforeDate()) {
            $start = $start->max(Carbon::parse($lockBeforeDate));
        }
        $start = $start->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        // Baris yang jadwalnya terkait pengajuan tukar shift TIDAK ikut direset - dikeluarkan dari
        // daftar hapus (konsisten dengan sel yang dikunci di grid).
        $swapCells = $this->swapCells;

        $rows = $this->shiftAssignmentQuery()->whereIn('user_id', $this->employees->pluck('id'))
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

        $deleted = $this->shiftAssignmentQuery()->whereIn('id', $rows->pluck('id'))->delete();

        $this->editMode = false;
        $this->draft = [];

        unset($this->roster, $this->rosterLogs);

        Notification::make()
            ->title('Jadwal direset')
            ->body("{$deleted} baris roster {$this->periodLabel} dihapus.")
            ->success()
            ->send();
    }

    public function exportExcel()
    {
        abort_if($this->periodLocked, 403);

        $teamNames = collect($this->employeeTeamInfo)->map(fn ($info) => $info['name'])->all();

        return Excel::download(
            new ScheduleShiftRosterExport($this->schedule, $this->employees, $this->dates, $this->roster, $this->periodLabel, $teamNames, $this->teamGroupMode),
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
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);
        abort_if($this->periodLocked, 403);

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls']);

        $rows = Excel::toArray(null, $this->importFile->getRealPath())[0] ?? [];
        $dataRows = array_slice($rows, 3);

        // Kolom nama di posisi 0 (mode Baris - banner tim jadi baris pemisah, dilewati di loop
        // bawah karena namanya tidak match employee manapun DAN semua kolom tanggalnya kosong)
        // atau posisi 1 (mode Kolom - kolom 0 dipakai Tim, lihat ScheduleShiftRosterExport).
        // Diasumsikan file yang diimpor hasil export dengan $teamGroupMode SAAT INI - ganti mode
        // Baris/Kolom di antara export dan import bikin kolomnya salah baca.
        $nameColIdx = ($this->schedule->use_teams && $this->teamGroupMode === 'column') ? 1 : 0;

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
        // Detail per sel yang dilewati karena shift-nya bukan jatah Tim karyawan itu - bukan
        // cuma jumlahnya (2026-09-14, instruksi user: harus jelas jadwal MANA yang tidak sesuai,
        // bukan cuma "N sel dilewati"). Format "Nama - dd/mm: KODE".
        $blockedByReguDetails = [];
        $changed = 0;

        foreach ($dataRows as $row) {
            $name = trim((string) ($row[$nameColIdx] ?? ''));

            if ($name === '') {
                continue;
            }

            $employee = $employeesByName->get(mb_strtolower($name));

            if (! $employee) {
                // Baris banner tim (mis. "Regu A") juga tidak match nama karyawan manapun -
                // dibedakan dari nama BENERAN salah/typo lewat semua kolom tanggalnya kosong
                // (banner tidak pernah punya kode shift), biar tidak nyasar jadi warning "nama
                // tidak dikenal" yang membingungkan (2026-09-15).
                $allDateCellsEmpty = collect($this->dates)
                    ->every(fn ($date, $i) => trim((string) ($row[$nameColIdx + 1 + $i] ?? '')) === '');

                if (! $allDateCellsEmpty) {
                    $unknownNames[$name] = true;
                }

                continue;
            }

            foreach ($this->dates as $i => $date) {
                $code = trim((string) ($row[$nameColIdx + 1 + $i] ?? ''));

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

                // Shift-nya dikenal, TAPI bukan jatah Regu karyawan ini bulan itu - dilewati sama
                // seperti kode tidak dikenal, dilaporkan terpisah (2026-09-14, instruksi user).
                // Bukan validasi baru sebenarnya - setCell()/saveRoster() sudah nolak ini juga,
                // cuma di sini dilaporkan LEBIH AWAL saat import, bukan baru ketahuan pas Simpan.
                if ($value && $value !== 'off' && ! $this->isShiftAllowedForUser($employee->id, $value)) {
                    $blockedByReguDetails[] = "{$employee->name} - {$date->format('d/m')}: {$code}";

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

        if ($blockedByReguDetails) {
            // Dibatasi 8 baris pertama biar notifikasi tidak kepanjangan kalau pelanggarannya
            // banyak - sisanya cuma disebut jumlahnya.
            $shown = array_slice($blockedByReguDetails, 0, 8);
            $rest = count($blockedByReguDetails) - count($shown);

            $warnings[] = 'Shift tidak sesuai Tim (dilewati): '.implode('; ', $shown).($rest > 0 ? "; dan {$rest} lainnya" : '');
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
