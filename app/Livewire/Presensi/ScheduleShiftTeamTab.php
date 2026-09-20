<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftTeam;
use App\Models\Shift\ScheduleShiftTeamMember;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Tab "Regu" di ScheduleDetailPage — kelola pengelompokan karyawan jadwal shift ini (Kepala
 * Ruangan / pengelola jadwal) PER BULAN (bukan tanggal efektif bebas — disederhanakan 2026-09-12
 * atas instruksi user, regu biasanya diganti per bulan/rotasi). Lihat ScheduleShiftTeamMember.
 */
class ScheduleShiftTeamTab extends Component
{
    /** Batas bawah periode yang bisa dibuka/diisi - instruksi user 2026-09-13, sama nilainya
     * dengan ScheduleShiftRosterTab::MIN_PERIOD. */
    private const MIN_PERIOD = '2026-09';

    public Schedule $schedule;

    /** Format 'Y-m', default bulan berjalan — sama pola $period di ScheduleShiftRosterTab. */
    public string $period = '';

    public bool $showTeamModal = false;

    /** null = modal mode "Tambah Regu", terisi = mode "Edit Regu" (id regu yang diedit). */
    public ?string $editingTeamId = null;

    public string $teamFormName = '';

    public string $teamFormColor = '#042c6c';

    /** @var array<int, string> shift_type_id terpilih di modal - kosong = regu ini TIDAK dibatasi
     * (boleh semua shift schedule ini), lihat ScheduleShiftTeamMember::allowedShiftTypeIdsFor(). */
    public array $teamFormShiftTypeIds = [];

    /** @var array<string, string> user_id => team_id ('' = Tanpa Regu) untuk $period yang lagi dipilih */
    public array $assignForm = [];

    public function mount(Schedule $schedule): void
    {
        // Tab Tim boleh dilihat siapa pun yang boleh membuka halaman jadwal ini (gerbang
        // SchedulePolicy::view) - ViewAny:ScheduleShiftTeam tidak dicek lagi (2026-09-21, instruksi
        // user). Yang tetap digerbangi: kelola Tim (Create/Update/Delete:ScheduleShiftTeam),
        // atur shift Tim (AssignShiftTypes:ScheduleShiftTeam), dan saklar aktif/nonaktif Tim
        // (Update:Schedule, lihat canToggleTeams).
        abort_unless(Auth::user()->can('view', $schedule), 403);

        $this->schedule = $schedule;
        $this->period = max(now()->format('Y-m'), self::MIN_PERIOD);
        $this->syncAssignForm();
    }

    public function updatedPeriod(): void
    {
        if ($this->period < self::MIN_PERIOD) {
            $this->period = self::MIN_PERIOD;
        }

        $this->syncAssignForm();
    }

    private function syncAssignForm(): void
    {
        $this->assignForm = [];

        if ($this->periodLocked) {
            return;
        }

        // Carry-over bulan depan - logic bersama di ScheduleShiftTeamMember::autoCarryNextMonthFromCurrent()
        // (dipanggil juga dari ScheduleShiftRosterTab, instruksi user 2026-09-16: tidak boleh
        // tergantung tab mana yang dibuka duluan). Bulan 2+ ke depan sengaja TIDAK diwarisi -
        // lihat getPeriodLockedProperty(), sudah dikunci total sebelum sampai sini.
        ScheduleShiftTeamMember::autoCarryNextMonthFromCurrent($this->schedule, $this->employees, $this->period, Auth::id());

        foreach ($this->employees as $user) {
            $current = ScheduleShiftTeamMember::forPeriod($this->schedule->id, $user->id, $this->period);
            $this->assignForm[$user->id] = $current?->team_id ?? '';
        }
    }

    public function getPeriodLabelProperty(): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m', $this->period)->locale('id')->translatedFormat('F Y');
    }

    /** Regu 2+ bulan ke depan dari bulan berjalan dikunci TOTAL (instruksi user 2026-09-13) -
     * regu itu pasti belum ada dasarnya buat diisi (bukan cuma "belum diwariskan" seperti bulan
     * depan), jadi tidak ada gunanya dibuka form-nya. Berlaku buat SEMUA orang termasuk pengelola
     * penuh jadwal, beda dari canEditPeriod (yang cuma soal bulan LAMPAU). */
    public function getPeriodLockedProperty(): bool
    {
        return $this->period > now()->addMonth()->format('Y-m');
    }

    /** Saklar `schedules.use_teams` — sama pola activateTemplate()/deactivateTemplate() di
     * ScheduleShiftRotationTab. Default FALSE, tidak semua jadwal butuh dikelompokkan regu. */
    public function activateTeams(): void
    {
        abort_unless($this->canToggleTeams, 403);

        $this->schedule->update(['use_teams' => true]);
        $this->schedule->refresh();

        Notification::make()->title('Tim diaktifkan untuk jadwal ini')->success()->send();
    }

    /** Saklar aktif/nonaktif Tim untuk jadwal ini - hanya pemegang Update:Schedule (edit jadwal),
     * bukan Create:ScheduleShiftTeam (2026-09-21, instruksi user). Kelola isi Tim tetap canManage. */
    public function getCanToggleTeamsProperty(): bool
    {
        return Auth::user()->can('update', $this->schedule);
    }

    public function deactivateTeams(): void
    {
        abort_unless($this->canToggleTeams, 403);

        $this->schedule->update(['use_teams' => false]);
        $this->schedule->refresh();

        Notification::make()->title('Tim dinonaktifkan')->body('Data tim & keanggotaan yang sudah diisi tetap tersimpan kalau mau diaktifkan lagi nanti. Tabel Detail Penjadwalan kembali urut nama.')->success()->send();
    }

    /** Gerbang umum "boleh masuk mode kelola" (tombol Tambah Regu + kolom kelola anggota) —
     * ScheduleShiftTeamPolicy::create() (permission Create:ScheduleShiftTeam, grup "Jadwal
     * Shift" di /hak-akses). Aksi per regu (edit/hapus) dicek terpisah lewat can('update'/
     * 'delete', $team) - lihat openEditTeamModal/saveTeam/deleteTeam. Sengaja BUKAN
     * Update:ShiftScheduleAssignments (tab Detail Penjadwalan) - dipisah supaya bisa digrant beda
     * dari izin isi roster (lihat migration 2026_09_12_110000 kenapa keduanya biasanya sepaket by
     * default). TIDAK ada fallback Update:Schedule - lihat catatan mount(). */
    public function getCanManageProperty(): bool
    {
        return Auth::user()->can('create', ScheduleShiftTeam::class);
    }

    /** Gerbang KHUSUS "atur shift apa saja yang boleh dipakai Tim ini" (checkbox di modal) -
     * dipisah dari canManage (2026-09-14, instruksi user), permission AssignShiftTypes:
     * ScheduleShiftTeam sendiri. Beda dari canManage: bisa bikin/edit nama & warna Tim TANPA
     * otomatis boleh ganti daftar shift-nya. TIDAK ada fallback Update:Schedule - lihat catatan
     * mount(). */
    public function getCanAssignShiftTypesProperty(): bool
    {
        return Auth::user()->can('AssignShiftTypes:ScheduleShiftTeam');
    }

    /** Bulan yang sudah lewat tidak bisa diatur regunya sama sekali (2026-09-14 - dulu pengelola
     * penuh jadwal/Update:Schedule bebas, dicabut bareng semua fallback lain di class ini, lihat
     * catatan mount()). Kalau nanti ada kebutuhan role tertentu bebas koreksi bulan lampau, buat
     * permission ScheduleShiftTeam baru yang eksplisit - jangan pinjam Update:Schedule lagi. */
    public function getCanEditPeriodProperty(): bool
    {
        return $this->period >= now()->format('Y-m');
    }

    /** Sama persis query getEmployeesProperty di ScheduleShiftRosterTab — karyawan yang
     * muncul di roster jadwal ini (ScheduleAssignment.user_id langsung, bukan lewat jabatan). */
    public function getEmployeesProperty()
    {
        return ScheduleAssignment::where('schedule_id', $this->schedule->id)
            ->whereNotNull('user_id')
            ->with('user.profile')
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy(fn ($u) => $u->rosterSortKey())
            ->values();
    }

    public function getTeamsProperty()
    {
        // Eager load shiftTypes - dipakai buat tampilan read-only "shift apa aja yang boleh" di
        // baris daftar Tim (2026-09-14, instruksi user: bisa dilihat cukup dengan permission lihat
        // saja, TIDAK perlu bisa masuk mode kelola/AssignShiftTypes buat sekadar melihat).
        return ScheduleShiftTeam::where('schedule_id', $this->schedule->id)
            ->with('shiftTypes')
            ->orderBy('name')
            ->get();
    }

    /** Kode shift yang bisa dipilih buat batasan regu - Oncall ikut boleh (2026-09-14, assignable
     * roster biasa sekarang, sama seperti dropdown roster/rotasi). */
    public function getShiftTypeOptionsProperty()
    {
        return $this->schedule->shiftTypes;
    }

    /** @return array<string, ?ScheduleShiftTeam> user_id => regu di $period yang lagi dipilih */
    public function getMembershipProperty(): array
    {
        $rows = ScheduleShiftTeamMember::where('schedule_id', $this->schedule->id)
            ->where('period', $this->period)
            ->with('team')
            ->get()
            ->keyBy('user_id');

        $out = [];
        foreach ($this->employees as $user) {
            $out[$user->id] = $rows->get($user->id)?->team;
        }

        return $out;
    }

    /** Buka modal mode "Tambah Regu". */
    public function openCreateTeamModal(): void
    {
        abort_unless(Auth::user()->can('create', ScheduleShiftTeam::class), 403);

        $this->editingTeamId = null;
        $this->teamFormName = '';
        $this->teamFormColor = '#042c6c';
        $this->teamFormShiftTypeIds = [];
        $this->resetErrorBag(['teamFormName', 'teamFormColor']);
        $this->showTeamModal = true;
    }

    /** Buka modal mode "Edit Regu", prefill dari regu yang dipilih. */
    public function openEditTeamModal(string $teamId): void
    {
        $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->with('shiftTypes')->findOrFail($teamId);
        abort_unless(Auth::user()->can('update', $team), 403);

        $this->editingTeamId = $team->id;
        $this->teamFormName = $team->name;
        $this->teamFormColor = $team->color ?? '#042c6c';
        $this->teamFormShiftTypeIds = $team->shiftTypes->pluck('id')->all();
        $this->resetErrorBag(['teamFormName', 'teamFormColor']);
        $this->showTeamModal = true;
    }

    public function closeTeamModal(): void
    {
        $this->showTeamModal = false;
        $this->editingTeamId = null;
    }

    /** Simpan modal — bikin regu baru kalau editingTeamId kosong, update kalau terisi. */
    public function saveTeam(): void
    {
        $this->validate([
            'teamFormName' => ['required', 'string', 'max:100'],
            'teamFormColor' => ['required', 'string', 'max:20'],
        ], [], ['teamFormName' => 'nama regu']);

        if ($this->editingTeamId === null) {
            abort_unless(Auth::user()->can('create', ScheduleShiftTeam::class), 403);

            $team = ScheduleShiftTeam::create([
                'schedule_id' => $this->schedule->id,
                'name' => $this->teamFormName,
                'color' => $this->teamFormColor,
                'created_by' => Auth::id(),
            ]);

            Notification::make()->title('Tim berhasil dibuat')->success()->send();
        } else {
            $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->findOrFail($this->editingTeamId);
            abort_unless(Auth::user()->can('update', $team), 403);

            $team->update(['name' => $this->teamFormName, 'color' => $this->teamFormColor]);

            Notification::make()->title('Tim berhasil diperbarui')->success()->send();
        }

        // Gerbang TERPISAH dari create/update nama&warna Tim di atas (2026-09-14, instruksi user -
        // permission AssignShiftTypes:ScheduleShiftTeam sendiri). Yang tidak punya izin ini
        // dilewati begitu saja (bukan abort) - field-nya sudah disembunyikan di blade
        // (canAssignShiftTypes), jadi teamFormShiftTypeIds tidak pernah keisi dari form untuk
        // user itu; skip di sini mencegah nilai kosong itu diam-diam menimpa/menghapus daftar
        // shift yang sudah diatur orang lain. Kosong (buat yang BOLEH mengatur) = Tim ini TIDAK
        // dibatasi (boleh semua shift), lihat ScheduleShiftTeamMember::allowedShiftTypeIdsFor().
        if ($this->canAssignShiftTypes) {
            $team->shiftTypes()->sync($this->teamFormShiftTypeIds);
        }

        $this->closeTeamModal();
    }

    public function deleteTeam(string $teamId): void
    {
        $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->findOrFail($teamId);
        abort_unless(Auth::user()->can('delete', $team), 403);

        // Cascade DB menghapus keanggotaan terkait (schedule_shift_team_members.team_id) -
        // anggota otomatis jadi "Tanpa Regu", bukan error.
        $team->delete();

        Notification::make()->title('Tim berhasil dihapus')->success()->send();
    }

    public function assignMember(string $userId): void
    {
        abort_unless($this->canManage, 403);
        abort_unless($this->canEditPeriod, 403);
        abort_if($this->periodLocked, 403);

        $teamId = $this->assignForm[$userId] ?? null;
        abort_if($teamId === null, 404);

        $user = $this->employees->firstWhere('id', $userId);
        abort_unless($user, 404);

        if ($teamId === '') {
            ScheduleShiftTeamMember::removeForPeriod($this->schedule, $user, $this->period);
        } else {
            $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->findOrFail($teamId);
            ScheduleShiftTeamMember::setForPeriod($this->schedule, $user, $team, $this->period, Auth::id());
        }

        Notification::make()->title('Tim ' . $user->name . ' berhasil diperbarui')->success()->send();
    }

    public function render()
    {
        return view('livewire.presensi.schedule-shift-team-tab');
    }
}
