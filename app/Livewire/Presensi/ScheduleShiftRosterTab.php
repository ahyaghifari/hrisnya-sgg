<?php

namespace App\Livewire\Presensi;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\CustomPermissionEnum;
use App\Exports\Presensi\ScheduleShiftRosterExport;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleRosterChangeRequest;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use App\Models\Shift\ScheduleShiftRotation;
use App\Models\Shift\ScheduleShiftTeam;
use App\Models\Shift\ScheduleShiftTeamMember;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ShiftSwapRequests;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
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

    /** Alasan perubahan - wajib saat Simpan diajukan ke approval (bukan simpan langsung). */
    public string $changeReason = '';

    public bool $showLogs = false;

    /** Cara pembagian tim ditampilkan di grid — 'row' (default, baris pemisah colspan penuh
     * per tim, instruksi user 2026-09-12) atau 'column' (kolom rowspan di samping nama
     * karyawan, diminta user 2026-09-15 buat jadwal yang timnya sedikit/anggotanya banyak per
     * tim, biar tidak makan baris ekstra). Sejak 2026-09-21 disimpan di DB per jadwal
     * (schedules.team_group_mode) - berlaku sama untuk semua yang membuka jadwal ini dan ikut
     * dipakai export Excel; dulu session per browser. Property ini cuma cermin dari kolom itu. */
    public string $teamGroupMode = 'row';

    /** Yang boleh mengubah Baris/Kolom: Kepala Ruangan jadwal ini dan pemegang Update:Schedule -
     * pengaturan bersama, bukan preferensi pribadi, jadi tidak untuk semua viewer. */
    public function getCanSetTeamGroupModeProperty(): bool
    {
        return $this->schedule->isManagedBy(Auth::id()) || Auth::user()->can('update', $this->schedule);
    }

    public function setTeamGroupMode(string $mode): void
    {
        abort_unless($this->canSetTeamGroupMode, 403);

        $this->teamGroupMode = $mode === 'column' ? 'column' : 'row';

        $this->schedule->update(['team_group_mode' => $this->teamGroupMode]);
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
        $this->teamGroupMode = $schedule->team_group_mode === 'column' ? 'column' : 'row';

        // Carry-over Tim - updatedPeriod() (hook "period berubah") TIDAK pernah kepanggil pas
        // mount() pertama kali (Livewire cuma trigger updatedX begitu $period diubah SETELAHNYA),
        // jadi kalau ini kunjungan PERTAMA karu ke halaman ini dan period default-nya (bulan
        // berjalan) kebetulan yang mestinya mewarisi Tim dari bulan lalu, carry-nya kelewat kalau
        // cuma diandalkan dari updatedPeriod() saja (dilaporkan user 2026-09-30). Reuse method yang
        // sama biar tidak dobel logic - lihat ScheduleShiftTeamTab::mount() yang sudah benar dari
        // awal (manggil syncAssignForm() yang di dalamnya ada carry ini juga).
        $this->carryTeamForPeriod();
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

    /** Pengajuan tukar shift milik company JADWAL ini. ShiftSwapRequests (& baris jadwal yang dirujuknya)
     * pakai global scope company = company user login, jadi user yang boleh membuka jadwal company lain
     * (gerbang SchedulePolicy::view() di mount()) tidak melihat tukar shift sama sekali -> ikon hilang &
     * sel serah-shift N/A di production walau data ada (console/tinker skip scope, makanya terlihat benar). */
    private function swapQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ShiftSwapRequests::withoutGlobalScope('company')->where('company_id', $this->schedule->company_id);
    }

    public function updatedPeriod(): void
    {
        if ($this->period < self::MIN_PERIOD) {
            $this->period = self::MIN_PERIOD;
        }

        $this->rotationStartDate = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();

        $this->carryTeamForPeriod();
    }

    /** Carry-over Tim ke $period (bulan berjalan atau bulan depan) dari bulan sebelumnya - logic
     * bersama sama ScheduleShiftTeamTab, TIDAK boleh tergantung tab mana yang dibuka duluan
     * (instruksi user 2026-09-16), dan TIDAK boleh tergantung updatedPeriod() sempat kepanggil
     * atau tidak (instruksi user 2026-09-30, lihat mount()). No-op sendiri kalau $period di luar
     * bulan berjalan/depan (lihat ScheduleShiftTeamMember::autoCarryNextMonthFromCurrent()); guard
     * use_teams di sini cuma hemat query kalau jadwal ini memang tidak pakai Tim sama sekali. */
    private function carryTeamForPeriod(): void
    {
        if ($this->schedule->use_teams) {
            ScheduleShiftTeamMember::autoCarryNextMonthFromCurrent($this->schedule, $this->employees, $this->period, Auth::id());
            $this->forgetTeamCaches();
        }
    }

    /** $employees/$employeeTeamInfo/$teamGroupSizes di-cache Livewire selama 1 request (computed
     * property "getXProperty") - begitu keanggotaan Tim berubah di request yang sama (ganti tim,
     * carry-over bulan depan), HARUS dibuang semuanya. Kalau cuma employeeTeamInfo yang dibuang,
     * urutan karyawan (employees) masih urutan LAMA sementara nama tim-nya sudah yang BARU -> baris
     * pemisah tim muncul berulang & anggota tim berhamburan (dilaporkan user 2026-09-21). */
    private function forgetTeamCaches(): void
    {
        unset($this->employees, $this->employeeTeamInfo, $this->teamGroupSizes);
    }

    public function getPeriodLabelProperty(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)->locale('id')->translatedFormat('F Y');
    }

    public function getEmployeesProperty()
    {
        $assignments = ScheduleAssignment::where('schedule_id', $this->schedule->id)
            ->whereNotNull('user_id')
            ->with('user.profile')
            ->get();

        $employees = $assignments->pluck('user')->filter();

        // Urutan manual "Tanpa Tim" (schedule_assignments.sort_order, tingkat jadwal) - dipakai buat
        // karyawan yang tidak punya tim bulan ini ATAU semua karyawan kalau jadwal tidak pakai Tim.
        $orderNoTeam = $assignments->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('sort_order')->filter(fn ($v) => $v !== null)->min());

        // Dikelompokkan per regu (tab "Regu", diatur per bulan - pakai $period yang lagi dibuka di
        // sini juga) - urut nama regu; yang belum punya regu bulan ini nyempil paling akhir. Cuma
        // urutan baris yang berubah, mekanisme drag-select (rfRow = $loop->index di blade) tidak
        // kesentuh sama sekali, tetap jalan seperti biasa.
        $members = $this->schedule->use_teams
            ? ScheduleShiftTeamMember::where('schedule_id', $this->schedule->id)
                ->where('period', $this->period)
                ->with('team')
                ->get()
                ->keyBy('user_id')
            : collect();

        // Di dalam grup (tim / Tanpa Tim): urutan manual (sort_order) dulu, lalu yang belum diurut
        // menyusul urut tanggal bergabung terlama -> terbaru (User::rosterSortKey).
        return $employees
            ->sortBy(function ($u) use ($members, $orderNoTeam) {
                $member = $members->get($u->id);
                $team = $this->schedule->use_teams ? ($member?->team?->name ?? "\u{FFFF}") : '';
                $order = $member ? $member->sort_order : $orderNoTeam->get($u->id);

                return sprintf('%s|%06d|%s', $team, $order ?? 999999, $u->rosterSortKey());
            })
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
                'id' => $m->team->id,
                'name' => $m->team->name,
                'color' => $m->team->color,
                // null = regu ini TIDAK dibatasi (boleh semua shift), lihat
                // ScheduleShiftTeamMember::allowedShiftTypeIdsFor().
                'shift_type_ids' => $m->team->shiftTypes->pluck('id')->all() ?: null,
            ]])
            ->all();
    }

    /** user_id karyawan yang lagi dibuka popup "Ganti Tim"-nya (tombol di samping nama), null = tertutup. */
    public ?string $teamPickerUserId = null;

    /** Ganti tim lewat tabel ini - hanya Kepala Ruangan jadwal ini (schedules.head_user_id) dan
     * pemegang Update:Schedule (Edit Jadwal), instruksi user 2026-09-21. Batas bulan sama dengan
     * ScheduleShiftTeamTab::assignMember(): hanya bulan berjalan + bulan depan (bukan bulan lampau,
     * bukan 2+ bulan ke depan). Dimatikan selama editMode - urutan baris ikut berubah begitu tim
     * ganti, sedangkan draf sel & Isi Cepat mengandalkan posisi baris. */
    public function getCanChangeTeamProperty(): bool
    {
        return $this->schedule->use_teams
            && ! $this->editMode
            && $this->canManageOrder;
    }

    /** Menu titik-tiga di samping nama (ganti tim / urutkan) & pengurutan manual - Kepala Ruangan
     * jadwal ini atau pemegang Update:Schedule, bulan berjalan + bulan depan saja, dimatikan saat
     * editMode. Beda dari canChangeTeam: tidak butuh jadwal memakai Tim (Tanpa Tim juga bisa diurutkan). */
    public function getCanManageOrderProperty(): bool
    {
        return ! $this->editMode
            && ($this->schedule->isManagedBy(Auth::id()) || Auth::user()->can('update', $this->schedule))
            && $this->period >= now()->format('Y-m')
            && ! $this->periodLocked;
    }

    public function openTeamPicker(string $userId): void
    {
        abort_unless($this->canChangeTeam, 403);
        abort_unless($this->employees->contains('id', $userId), 404);

        $this->teamPickerUserId = $userId;
    }

    public function closeTeamPicker(): void
    {
        $this->teamPickerUserId = null;
    }

    /** $teamId '' = Tanpa Tim. */
    public function setMemberTeam(string $teamId): void
    {
        abort_unless($this->canChangeTeam, 403);

        $user = $this->employees->firstWhere('id', $this->teamPickerUserId);
        abort_unless($user, 404);

        if ($teamId === '') {
            ScheduleShiftTeamMember::removeForPeriod($this->schedule, $user, $this->period);
            // Urutan "Tanpa Tim" lama tidak relevan buat yang baru keluar tim - jatuh ke paling belakang.
            ScheduleAssignment::where('schedule_id', $this->schedule->id)->where('user_id', $user->id)->update(['sort_order' => null]);
        } else {
            $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->findOrFail($teamId);
            ScheduleShiftTeamMember::setForPeriod($this->schedule, $user, $team, $this->period, Auth::id());
        }

        $this->teamPickerUserId = null;
        $this->forgetTeamCaches();

        Notification::make()->title('Tim ' . $user->name . ' untuk ' . $this->periodLabel . ' berhasil diperbarui')->success()->send();
    }

    /** Tombol "Urutkan per Tanggal Bergabung" (di samping Reset Jadwal) - khusus pemegang Update:Schedule,
     * bulan berjalan + bulan depan, tidak saat editMode. Kepala Ruangan biasa tidak (beda dari
     * canManageOrder yang untuk pindah satu-satu). */
    public function getCanResetOrderProperty(): bool
    {
        return ! $this->editMode
            && Auth::user()->can('update', $this->schedule)
            && $this->period >= now()->format('Y-m')
            && ! $this->periodLocked;
    }

    /** Kembalikan urutan SEMUA grup (tiap tim di bulan ini + Tanpa Tim) ke tanggal bergabung terlama
     * dulu (User::rosterSortKey) dan simpan sebagai nomor eksplisit 1..n - jadi urutan default
     * langsung tersimpan di DB, tidak lagi dihitung ulang tiap dibuka. */
    public function resetOrderByJoinDate(): void
    {
        abort_unless($this->canResetOrder, 403);

        $groups = $this->employees->unique('id')
            ->groupBy(fn ($u) => (string) ($this->employeeTeamInfo[$u->id]['id'] ?? ''));

        DB::transaction(function () use ($groups) {
            foreach ($groups as $teamId => $users) {
                foreach ($users->sortBy(fn ($u) => $u->rosterSortKey())->values() as $i => $u) {
                    if ($teamId !== '') {
                        ScheduleShiftTeamMember::where('schedule_id', $this->schedule->id)
                            ->where('user_id', $u->id)->where('period', $this->period)
                            ->update(['sort_order' => $i + 1]);
                    } else {
                        ScheduleAssignment::where('schedule_id', $this->schedule->id)
                            ->where('user_id', $u->id)
                            ->update(['sort_order' => $i + 1]);
                    }
                }
            }
        });

        $this->forgetTeamCaches();

        Notification::make()->title('Urutan karyawan dikembalikan ke tanggal bergabung')->success()->send();
    }

    /** Popup "Urutkan": karyawan yang dipindah, posisi ('before'/'after') dan karyawan patokannya. */
    public ?string $reorderUserId = null;

    public string $reorderPosition = 'before';

    public string $reorderTargetId = '';

    /** Karyawan segrup (tim yang sama, atau sama-sama Tanpa Tim) dengan $userId, urut tampilan sekarang. */
    public function groupMembersOf(string $userId)
    {
        $teamId = $this->employeeTeamInfo[$userId]['id'] ?? null;

        return $this->employees
            ->unique('id')
            ->filter(fn ($u) => ($this->employeeTeamInfo[$u->id]['id'] ?? null) === $teamId)
            ->values();
    }

    public function openReorder(string $userId): void
    {
        abort_unless($this->canManageOrder, 403);
        abort_unless($this->groupMembersOf($userId)->count() > 1 && $this->employees->contains('id', $userId), 404);

        $this->reorderUserId = $userId;
        $this->reorderPosition = 'before';
        $this->reorderTargetId = '';
        $this->resetErrorBag('reorderTargetId');
    }

    public function closeReorder(): void
    {
        $this->reorderUserId = null;
    }

    /** Pindahkan karyawan ke sebelum/sesudah patokan DI DALAM grupnya, lalu simpan urutan SELURUH grup
     * (1..n) - di tim: schedule_shift_team_members.sort_order (per bulan); Tanpa Tim (atau jadwal tanpa
     * Tim): schedule_assignments.sort_order. Semua anggota grup dapat nomor eksplisit supaya hasilnya
     * pasti, tidak bergantung urutan default anggota lain. */
    public function saveReorder(): void
    {
        abort_unless($this->canManageOrder, 403);

        $this->validate([
            'reorderPosition' => ['required', 'in:before,after'],
            'reorderTargetId' => ['required', 'string'],
        ], [], ['reorderTargetId' => 'karyawan patokan']);

        $userId = (string) $this->reorderUserId;
        $group = $this->groupMembersOf($userId);

        abort_unless($group->contains('id', $userId) && $group->contains('id', $this->reorderTargetId) && $this->reorderTargetId !== $userId, 404);

        $ids = $group->pluck('id')->reject(fn ($id) => $id === $userId)->values()->all();
        $at = array_search($this->reorderTargetId, $ids, true) + ($this->reorderPosition === 'after' ? 1 : 0);
        array_splice($ids, $at, 0, [$userId]);

        $teamId = $this->employeeTeamInfo[$userId]['id'] ?? null;

        DB::transaction(function () use ($ids, $teamId) {
            foreach ($ids as $i => $id) {
                if ($teamId) {
                    ScheduleShiftTeamMember::where('schedule_id', $this->schedule->id)
                        ->where('user_id', $id)->where('period', $this->period)
                        ->update(['sort_order' => $i + 1]);
                } else {
                    ScheduleAssignment::where('schedule_id', $this->schedule->id)
                        ->where('user_id', $id)
                        ->update(['sort_order' => $i + 1]);
                }
            }
        });

        $name = $group->firstWhere('id', $userId)?->name;
        $this->reorderUserId = null;
        $this->forgetTeamCaches();

        Notification::make()->title('Urutan ' . $name . ' berhasil diperbarui')->success()->send();
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

    /** @return array<string, array<string, string>> [user_id => [date => draft shift_type_id atau
     * 'off']] - CUMA sel yang lagi ada pengajuan belum diputuskan (draft_request_id terisi), lihat
     * ScheduleShiftAssignment::draft_request_id / ScheduleRosterChangeService. Dipakai overlay di
     * atas $roster (approved) buat preview di grid - TIDAK dipakai presensi (ScheduleResolver
     * default tetap baca kolom approved). */
    public function getDraftRosterProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->subDays($this->leadDays)->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        $userIds = $this->employees->pluck('id')->all();

        $rows = $this->shiftAssignmentQuery()->whereIn('user_id', $userIds)
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('draft_request_id')
            ->get();

        $draft = [];

        foreach ($rows as $row) {
            $draft[$row->user_id][$row->date->toDateString()] = $row->draft_off_day ? 'off' : ($row->draft_shift_type_id ?? '');
        }

        return $draft;
    }

    /** $roster (approved) dengan sel yang punya draft ditimpa nilai draft-nya - "apa yang
     * ditampilkan ke user" di grid (baik mode lihat maupun basis awal $draft pas mulai edit). */
    public function getDisplayRosterProperty(): array
    {
        $roster = $this->roster;

        foreach ($this->draftRoster as $userId => $dates) {
            foreach ($dates as $date => $value) {
                $roster[$userId][$date] = $value;
            }
        }

        return $roster;
    }

    /** Daftar sel yang diajukan [nama, tanggal, dari, ke, alasan] - buat banner approver biar
     * kelihatan perubahannya dari shift apa ke shift apa & kenapa (bukan cuma sel dim di grid). */
    public function getPendingChangesProperty(): array
    {
        $types = $this->schedule->shiftTypes->keyBy('id');
        $label = fn (?string $v) => match (true) {
            $v === null || $v === '' => 'Kosong',
            $v === 'off' => 'Libur',
            default => $types->get($v)?->code ?? '?',
        };
        $names = $this->employees->pluck('name', 'id');

        $rows = $this->shiftAssignmentQuery()
            ->whereIn('user_id', $names->keys()->all())
            ->whereNotNull('draft_request_id')
            ->get();

        return $rows->map(fn ($row) => [
            'name' => $names->get($row->user_id, '-'),
            'date' => $row->date->toDateString(),
            'from' => $label($row->off_day ? 'off' : $row->shift_type_id),
            'to' => $label($row->draft_off_day ? 'off' : $row->draft_shift_type_id),
            'reason' => $row->draft_reason,
        ])->sortBy(fn ($c) => $c['date'] . '|' . $c['name'])->values()->all();
    }

    /** Pengajuan perubahan jadwal yang masih menunggu keputusan untuk jadwal INI (kalau ada - 1
     * schedule cuma boleh punya 1 yang in_review, lihat ScheduleRosterChangeService). Dipakai
     * banner "Menunggu Persetujuan" di atas grid & gerbang approveRosterChange()/rejectRosterChange(). */
    public function getOpenRosterChangeRequestProperty(): ?ScheduleRosterChangeRequest
    {
        return ScheduleRosterChangeRequest::where('schedule_id', $this->schedule->id)
            ->where('status', 'in_review')
            ->with(['requester', 'approvalSnapshot'])
            ->first();
    }

    /** User ini approver step SEKARANG untuk pengajuan yang lagi terbuka - kalau true, banner
     * dapat tombol Setuju/Tolak; kalau false (mis. karu pengaju sendiri, atau bukan approver step
     * ini), banner cuma informasi "menunggu atasan". */
    public function getCanReviewRosterChangeProperty(): bool
    {
        $request = $this->openRosterChangeRequest;

        if (! $request || ! $request->approvalSnapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($request->approvalSnapshot->id, Auth::user());
    }

    public function approveRosterChange(): void
    {
        abort_unless($this->canReviewRosterChange, 403);

        $request = $this->openRosterChangeRequest;

        try {
            app(ApprovalFlowService::class)->advanceStep($request->approvalSnapshot, Auth::id(), null, 'approved');
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menyetujui perubahan jadwal ini')->danger()->send();

            return;
        }

        unset($this->roster, $this->draftRoster, $this->displayRoster, $this->openRosterChangeRequest, $this->rosterLogs);

        Notification::make()->title('Perubahan jadwal disetujui')->success()->send();
    }

    public function rejectRosterChange(): void
    {
        abort_unless($this->canReviewRosterChange, 403);

        $request = $this->openRosterChangeRequest;

        try {
            app(ApprovalFlowService::class)->advanceStep($request->approvalSnapshot, Auth::id(), null, 'rejected');
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menolak perubahan jadwal ini')->danger()->send();

            return;
        }

        unset($this->roster, $this->draftRoster, $this->displayRoster, $this->openRosterChangeRequest, $this->rosterLogs);

        Notification::make()->title('Perubahan jadwal ditolak')->success()->send();
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
     * Global scope company dibuka (swapQuery() + relasi) - tanpa itu akun beda company tidak melihat
     * ikon sama sekali walau datanya ada.
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
        $swaps = $this->swapQuery()->whereIn('status', ['in_review', 'approved'])
            ->whereHas('reqSchedule', fn ($q) => $q->withoutGlobalScope('company')
                ->whereHas('shiftType', fn ($q2) => $q2->where('schedule_id', $this->schedule->id)))
            ->where(function ($q) use ($start, $end) {
                $q->whereHas('reqSchedule', fn ($q2) => $q2->withoutGlobalScope('company')->whereBetween('date', [$start, $end]))
                    ->orWhereHas('targetSchedule', fn ($q2) => $q2->withoutGlobalScope('company')->whereBetween('date', [$start, $end]));
            })
            ->with([
                'reqSchedule' => fn ($q) => $q->withoutGlobalScope('company')->select('id', 'user_id', 'date'),
                'targetSchedule' => fn ($q) => $q->withoutGlobalScope('company')->select('id', 'user_id', 'date'),
            ])
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

    /** Sel kosong hasil tukar beda tanggal APPROVED: pemiliknya sudah menyerahkan shift di tanggal itu
     * (baris pindah ke rekan), tapi grid tetap menampilkan kode shift yang ditukar (redup + ikon,
     * read-only), bukan N/A (instruksi user 2026-10-02). Key "{user_id}|{Y-m-d}" => shift_type_id.
     * Sumber berurutan: baris jadwal yang dirujuk -> Double Shift hasil tukar -> kode yang DITERIMA dari
     * rekan. Tidak ada yang cocok -> sel dibiarkan N/A.
     *
     * @return array<string, string>
     */
    public function getSwapGhostsProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        $swaps = $this->swapQuery()->where('status', 'approved')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('req_date', [$start, $end])->orWhereBetween('target_date', [$start, $end]);
            })
            ->with([
                'reqSchedule' => fn ($q) => $q->withoutGlobalScope('company')->select('id', 'shift_type_id'),
                'targetSchedule' => fn ($q) => $q->withoutGlobalScope('company')->select('id', 'shift_type_id'),
            ])
            ->get();

        $ghosts = [];

        foreach ($swaps as $s) {
            if (! $s->target_date || $s->target_date->equalTo($s->req_date)) {
                continue;
            }

            foreach ([[$s->requester_id, $s->req_date, $s->reqSchedule, $s->targetSchedule], [$s->target_id, $s->target_date, $s->targetSchedule, $s->reqSchedule]] as [$uid, $d, $slot, $otherSlot]) {
                $date = $d->toDateString();

                $typeId = $slot?->shift_type_id
                    ?? ExtraShiftAssignment::withoutGlobalScope('company')
                        ->where('origin_shift_swap_request_id', $s->id)
                        ->whereDate('date', $date)
                        ->where('user_id', '!=', $uid)
                        ->value('shift_type_id')
                    ?? $otherSlot?->shift_type_id;

                if ($typeId && $date >= $start && $date <= $end) {
                    $ghosts[$uid . '|' . $date] = $typeId;
                }
            }
        }

        return $ghosts;
    }

    /** Sel roster yang off_day-nya KARENA Cuti/Izin disetujui otomatis (schedule_status
     * 'cuti'/'izin', diisi AttendaceReqService::markRosterDayOff()) - dikunci dari edit manual/Isi
     * Cepat/Import/Reset, sama pola dengan $swapCells, supaya tidak ketimpa balik jadi shift kerja
     * biasa lewat jalur bulk (instruksi user 2026-09-24). Beda dari Libur manual biasa yang
     * schedule_status-nya null - itu tetap bebas diedit seperti sebelumnya.
     *
     * @return array<string, string> "{user_id}|{Y-m-d}" => 'cuti'|'izin'
     */
    public function getLeaveCellsProperty(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->subDays($this->leadDays)->toDateString();
        $end = Carbon::createFromFormat('Y-m', $this->period)->endOfMonth()->toDateString();

        return $this->shiftAssignmentQuery()->whereIn('user_id', $this->employees->pluck('id'))
            ->whereBetween('date', [$start, $end])
            ->whereIn('schedule_status', ['cuti', 'izin'])
            ->get(['user_id', 'date', 'schedule_status'])
            ->mapWithKeys(fn ($row) => [$row->user_id.'|'.$row->date->toDateString() => $row->schedule_status])
            ->all();
    }

    /** Sel Cuti/Izin ($leaveCells) cuma boleh DITIMPA oleh pemegang Update:Schedule ("Edit
     * Jadwal") - permission yang sama dengan canEditPastDates (instruksi user 2026-09-24).
     * Kepala Ruangan yang cuma punya Update:ShiftScheduleAssignments TETAP terkunci total dari
     * sel ini lewat semua jalur (manual, Isi Cepat, Import, Reset). */
    public function getCanOverrideLeaveLockProperty(): bool
    {
        return $this->canEditPastDates;
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

    /** Masuk mode edit — salin roster YANG DITAMPILKAN saat ini ke $draft (sel kosong default ''),
     * supaya select di grid punya nilai awal yang benar sebelum diubah user. Pakai displayRoster
     * (approved + overlay draft yang lagi diajukan), bukan roster approved polos - kalau ada
     * pengajuan lama yang belum diputuskan, karu/atasan lihat & lanjut edit proposalnya sendiri,
     * bukan mulai dari approved seolah pengajuan lama tidak ada. */
    public function startEdit(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);
        abort_if($this->periodLocked, 403);
        // Approver yang lagi ada pengajuan menunggu keputusannya di jadwal ini wajib
        // Setujui/Tolak dulu lewat banner, bukan mulai edit sendiri di atas draft yang belum
        // diputuskan - instruksi user. Jangan cuma sembunyikan tombol di UI.
        abort_if($this->canReviewRosterChange, 403);

        $roster = $this->displayRoster;

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
        $this->changeReason = '';
    }

    /** Tulis $draft ke DB SEKALIGUS lewat batch (upsert + delete + insert log), BUKAN loop
     * setCell() per sel (dulu 1 SELECT + 1 write + 1 log INSERT per sel - grid 30 karyawan x 30
     * hari yang banyak berubah gampang tembus >1000 query dalam 1 klik Simpan, lambat sekali -
     * dikeluhkan user 2026-09-14). setCell() TETAP ada apa adanya buat edit 1 sel langsung
     * (dipanggil test & alur lain) - gerbang/aturannya (swap-lock, jatah Tim) disalin di sini
     * supaya hasil akhirnya identik, cuma eksekusi DB-nya dibatch lewat persistRosterBatch(). Tidak
     * ada lagi gerbang tanggal lampau di sini (dulu ada, dicabut 2026-09-28) - yang tidak punya
     * Update:Schedule sekarang boleh ajukan tanggal berapa pun lewat approval (lihat cabang
     * ! canEditPastDates di bawah), toh tidak pernah tertulis langsung sampai disetujui atasannya. */
    public function saveRoster(): void
    {
        abort_unless(Auth::user()->can('Update:ShiftScheduleAssignments'), 403);
        abort_if($this->periodLocked, 403);

        if (! $this->editMode) {
            return;
        }

        // displayRoster (approved + overlay draft), bukan roster approved polos - biar sel yang
        // "kembali ke kondisi awal" (approved) TERDETEKSI sebagai perubahan dari draft yang lagi
        // pending (before=nilai draft lama), bukan malah dianggap no-op dan ke-skip diam-diam
        // (ScheduleRosterChangeService yang nentuin itu revert-ke-approved -> draft dibatalkan).
        $currentRoster = $this->displayRoster;
        $swapCells = $this->swapCells;
        $leaveCells = $this->leaveCells;
        $blockedBySwap = 0;
        $blockedByLeave = 0;
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
                if (isset($swapCells[$userId . '|' . $date]) && ! $this->canOverrideLeaveLock) {
                    $blockedBySwap++;
                    continue;
                }

                // Sel Cuti/Izin disetujui otomatis cuma boleh ditimpa pemegang Update:Schedule
                // (Edit Jadwal) - gerbang di blade bisa dilewati lewat request yang di-craft, jadi
                // dicek ulang (instruksi user 2026-09-24).
                if (isset($leaveCells[$userId . '|' . $date]) && ! $this->canOverrideLeaveLock) {
                    $blockedByLeave++;
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

        // Yang tidak punya Update:Schedule (Edit Jadwal) - biasanya Kepala Ruangan - tidak boleh
        // menulis roster langsung, perubahannya diajukan dulu ke atasannya lewat modul approval
        // (instruksi user 2026-09-28), baru benar-benar ditulis begitu disetujui (lihat
        // ApprovalFlowService::advanceStep() cabang SCHEDULE_ROSTER_CHANGE). Yang punya
        // Update:Schedule tetap langsung tersimpan seperti biasa.
        if ($pending && ! $this->canEditPastDates) {
            $reason = trim($this->changeReason);

            if ($reason === '') {
                Notification::make()->title('Isi alasan perubahan jadwal dulu.')->danger()->send();

                return;
            }

            try {
                app(\App\Services\Shift\ScheduleRosterChangeService::class)
                    ->submitForApproval($this->schedule, $this->period, $pending, Auth::user(), $reason);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title('Gagal mengajukan perubahan jadwal: ' . $e->getMessage())
                    ->danger()
                    ->send();

                return;
            }

            $this->editMode = false;
            $this->draft = [];
            $this->changeReason = '';

            unset($this->draftRoster, $this->displayRoster, $this->rosterLogs);

            Notification::make()
                ->title('Perubahan jadwal diajukan untuk persetujuan')
                ->body("{$changed} sel diajukan ke HC/Kepegawaian, belum tersimpan sampai disetujui.")
                ->success()
                ->send();

            return;
        }

        if ($pending) {
            $this->persistRosterBatch($pending);
        }

        $this->editMode = false;
        $this->draft = [];

        // Buang memoisasi computed — kalau tidak, render() di request yang sama masih baca
        // roster/log versi cache SEBELUM tulisan setCell(), grid & Riwayat baru muncul pas refresh.
        unset($this->roster, $this->draftRoster, $this->displayRoster, $this->rosterLogs);

        Notification::make()
            ->title('Jadwal berhasil disimpan')
            ->body(
                "{$changed} sel diubah."
                . ($blockedBySwap ? " {$blockedBySwap} sel dilewati karena terkait pengajuan tukar shift." : '')
                . ($blockedByLeave ? " {$blockedByLeave} sel dilewati karena Cuti/Izin disetujui." : '')
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
    /** Eksekusi DB-nya sendiri diekstrak ke RosterBatchWriter (2026-09-28) - dipakai juga oleh
     * ScheduleRosterChangeService saat approval disetujui, lihat docblock class itu. */
    private function persistRosterBatch(array $pending): void
    {
        app(\App\Services\Shift\RosterBatchWriter::class)->write($this->schedule, $pending, Auth::id());
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
     *   isShiftAllowedForUser(); atau sel ini Cuti/Izin disetujui otomatis, lihat
     *   $this->leaveCells), tidak ada apa pun ditulis/dilog. */
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

        // Cuti/Izin disetujui otomatis cuma boleh ditimpa pemegang Update:Schedule (Edit Jadwal)
        // - instruksi user 2026-09-24.
        if ($existing && in_array($existing->schedule_status, ['cuti', 'izin'], true) && ! $this->canOverrideLeaveLock) {
            return false;
        }

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

        if ($value === 'off' && $before !== 'off') {
            $this->refundLeaveDayIfBecameOffDay($userId, $date);
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

    /** Kalau tanggal $date buat $userId BARU jadi Libur (bukan lewat approval Cuti/Izin sendiri -
     * itu jalur lain, AttendaceReqService::markRosterDayOff(), tidak dipanggil di sini sama
     * sekali) lewat roster manual/Isi Cepat/Import/Generate, refund 1 hari ke Cuti Tahunan/Haid
     * APPROVED yang mencakup tanggal itu - karyawan sudah punya hak libur hari itu, jangan sampai
     * kepotong cuti juga (instruksi user 2026-09-24). Cuti Melahirkan/Keguguran TIDAK direfund -
     * dihitung hari kalender kontinu (LeaveTypeCategoryEnum::countsCalendarDays()), bukan hari
     * kerja, jadi konsep "hari ini kebetulan libur" tidak relevan buat jenis itu. */
    private function refundLeaveDayIfBecameOffDay(string $userId, string $date): void
    {
        $detail = AttendanceRequestLeaveDetails::query()
            ->whereIn('category', [LeaveTypeCategoryEnum::TAHUNAN->value, LeaveTypeCategoryEnum::HAID->value])
            ->where('total_days', '>', 0)
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereNotNull('end_date')->where('start_date', '<=', $date)->where('end_date', '>=', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereNull('end_date')->where('start_date', $date);
                });
            })
            ->whereIn('request_id', AttendanceRequests::query()->select('id')
                ->where('user_id', $userId)->where('type', 'leave')->where('status', 'approved'))
            ->first();

        $detail?->decrement('total_days');
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

                // Baris baru (dijamin belum ada roster sebelumnya lewat $existingKeys di atas) -
                // kalau ternyata hasil rotasi Libur, refund kuota Cuti Tahunan/Haid yang kebetulan
                // sudah kepotong buat tanggal ini (instruksi user 2026-09-24).
                if ($rotationRow->off_day) {
                    $this->refundLeaveDayIfBecameOffDay($employee->id, $dateStr);
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
        // DINONAKTIFKAN sementara (instruksi user 2026-09-29) - method ini menulis/menghapus
        // langsung ke DB, bypass alur approval Perubahan Jadwal sama sekali (beda dari saveRoster()
        // yang sudah lewat ScheduleRosterChangeService kalau user tidak punya Update:Schedule).
        // Tombolnya sudah disembunyikan di blade, abort di sini jaga-jaga request manual.
        abort(403, 'Reset Jadwal sedang dinonaktifkan sementara.');

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
            // Cuti/Izin disetujui otomatis juga TIDAK ikut direset, kecuali oleh pemegang
            // Update:Schedule (Edit Jadwal) - instruksi user 2026-09-24.
            ->reject(fn ($row) => isset($swapCells[$row->user_id . '|' . $row->date->toDateString()])
                || (in_array($row->schedule_status, ['cuti', 'izin'], true) && ! $this->canOverrideLeaveLock));

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

        // Sel yang masih in_review ikut diexport (nilai draft menimpa approved) - sama aturan
        // ScheduleResolver: draft kosong tidak menghapus nilai approved.
        $roster = $this->roster;
        foreach ($this->draftRoster as $userId => $dates) {
            foreach (array_filter($dates, fn ($v) => $v !== '') as $date => $value) {
                $roster[$userId][$date] = $value;
            }
        }

        return Excel::download(
            new ScheduleShiftRosterExport($this->schedule, $this->employees, $this->dates, $roster, $this->periodLabel, $teamNames, $this->teamGroupMode, $this->leaveCells),
            'detail-penjadwalan-'.str($this->schedule->name)->slug().'-'.$this->period.'.xlsx'
        );
    }

    /** Baca ulang file hasil exportExcel() yang sudah diedit user - baris/kolom grid HARUS sama
     * posisinya kayak export (baris 1-2 judul, baris 3 header, baris 4.. data; kolom tanggal
     * diasumsikan urutan 1..akhir bulan periode ini, bukan dibaca dari isi kolomnya). Employee
     * dicocokkan by nama (exact, case-insensitive), shift dicocokkan by kode. Sel kosong =
     * kosongkan, "L" = Libur, "C"/"Izin" = Cuti/Izin (kode yang dipakai export, dibaca balik
     * sebagai off biasa), kode tidak dikenal dilewati (dilaporkan sebagai warning).
     *
     * Sel yang statusnya Cuti/Izin disetujui otomatis ($this->leaveCells) SENGAJA dilewati apa pun
     * kodenya di file - tidak boleh ketimpa balik jadi shift kerja biasa lewat bulk import, KECUALI
     * pemegang Update:Schedule (Edit Jadwal) - instruksi user 2026-09-24. Dilaporkan sebagai
     * warning sama seperti blockedByRegu buat yang tidak punya izin override.
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
        // Sama seperti startEdit() - approver yang lagi ada pengajuan menunggu wajib
        // Setujui/Tolak dulu, bukan malah import di atas draft yang belum diputuskan.
        abort_if($this->canReviewRosterChange, 403);

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls']);

        $rows = Excel::toArray(null, $this->importFile->getRealPath())[0] ?? [];
        $dataRows = array_slice($rows, 3);

        // Kolom nama dicari lewat header "Karyawan" di baris 3 - sejak layout export 2026-09-21 grid
        // roster mulai di kolom F (tabel legenda di kiri), dan mode Kolom menambah kolom Tim di
        // depannya, jadi posisinya tidak bisa di-hardcode lagi. Banner tim (mode Baris) dilewati di
        // loop bawah karena namanya tidak match employee manapun DAN semua kolom tanggalnya kosong.
        // Fallback ke aturan lama (posisi 0/1 menurut mode) kalau header tidak ketemu.
        $nameColIdx = collect($rows[2] ?? [])->search(fn ($cell) => mb_strtolower(trim((string) $cell)) === 'karyawan');
        if ($nameColIdx === false) {
            $nameColIdx = ($this->schedule->use_teams && $this->teamGroupMode === 'column') ? 1 : 0;
        }

        $employeesByName = $this->employees->keyBy(fn ($employee) => mb_strtolower(trim($employee->name)));
        $shiftTypesByCode = $this->schedule->shiftTypes->keyBy(fn ($type) => mb_strtolower($type->code));
        $roster = $this->roster;
        $leaveCells = $this->leaveCells;

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
        // Sama pola dengan blockedByReguDetails - sel Cuti/Izin disetujui otomatis, dilaporkan
        // terpisah biar jelas jadwal MANA yang tidak ikut kesentuh import (instruksi 2026-09-24).
        $blockedByLeaveDetails = [];
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

                // Sel Cuti/Izin disetujui otomatis dilewati DULUAN, sebelum kode-nya diparse sama
                // sekali - apa pun isi kolomnya di file (biasanya "C"/"Izin" hasil export, tapi
                // kode lain pun tetap dilewati), tidak boleh ikut ketimpa lewat import, KECUALI
                // yang mengimpor pemegang Update:Schedule (Edit Jadwal) - instruksi 2026-09-24.
                if (isset($leaveCells[$employee->id.'|'.$date->toDateString()]) && ! $this->canOverrideLeaveLock) {
                    if ($code !== '' && ! in_array(mb_strtolower($code), ['c', 'i', 'izin'], true)) {
                        $blockedByLeaveDetails[] = "{$employee->name} - {$date->format('d/m')}: {$code}";
                    }

                    continue;
                }

                $value = match (true) {
                    $code === '' => '',
                    in_array(mb_strtolower($code), ['l', 'c', 'i', 'izin'], true) => 'off',
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

        if ($blockedByLeaveDetails) {
            $shown = array_slice($blockedByLeaveDetails, 0, 8);
            $rest = count($blockedByLeaveDetails) - count($shown);

            $warnings[] = 'Sudah Cuti/Izin disetujui, tidak diubah (dilewati): '.implode('; ', $shown).($rest > 0 ? "; dan {$rest} lainnya" : '');
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
