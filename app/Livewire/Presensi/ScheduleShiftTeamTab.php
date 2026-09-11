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
    public Schedule $schedule;

    /** Format 'Y-m', default bulan berjalan — sama pola $period di ScheduleShiftRosterTab. */
    public string $period = '';

    public bool $showTeamModal = false;

    /** null = modal mode "Tambah Regu", terisi = mode "Edit Regu" (id regu yang diedit). */
    public ?string $editingTeamId = null;

    public string $teamFormName = '';

    public string $teamFormColor = '#042c6c';

    /** @var array<string, string> user_id => team_id ('' = Tanpa Regu) untuk $period yang lagi dipilih */
    public array $assignForm = [];

    public function mount(Schedule $schedule): void
    {
        // Gerbang view sama parent ScheduleDetailPage (SchedulePolicy::view), lihat catatan
        // sama di ScheduleShiftRosterTab::mount().
        abort_unless(Auth::user()->can('view', $schedule), 403);

        $this->schedule = $schedule;
        $this->period = now()->format('Y-m');
        $this->syncAssignForm();
    }

    public function updatedPeriod(): void
    {
        $this->syncAssignForm();
    }

    private function syncAssignForm(): void
    {
        $this->assignForm = [];

        foreach ($this->employees as $user) {
            $current = ScheduleShiftTeamMember::forPeriod($this->schedule->id, $user->id, $this->period);
            $this->assignForm[$user->id] = $current?->team_id ?? '';
        }
    }

    public function getPeriodLabelProperty(): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m', $this->period)->locale('id')->translatedFormat('F Y');
    }

    /** Saklar `schedules.use_teams` — sama pola activateTemplate()/deactivateTemplate() di
     * ScheduleShiftRotationTab. Default FALSE, tidak semua jadwal butuh dikelompokkan regu. */
    public function activateTeams(): void
    {
        abort_unless(Auth::user()->can('update', $this->schedule) || Auth::user()->can('create', ScheduleShiftTeam::class), 403);

        $this->schedule->update(['use_teams' => true]);
        $this->schedule->refresh();

        Notification::make()->title('Regu diaktifkan untuk jadwal ini')->success()->send();
    }

    public function deactivateTeams(): void
    {
        abort_unless(Auth::user()->can('update', $this->schedule) || Auth::user()->can('create', ScheduleShiftTeam::class), 403);

        $this->schedule->update(['use_teams' => false]);
        $this->schedule->refresh();

        Notification::make()->title('Regu dinonaktifkan')->body('Data regu & keanggotaan yang sudah diisi tetap tersimpan kalau mau diaktifkan lagi nanti. Tabel Detail Penjadwalan kembali urut nama.')->success()->send();
    }

    /** Gerbang umum "boleh masuk mode kelola" (tombol Tambah Regu + kolom kelola anggota) —
     * ScheduleShiftTeamPolicy::create() (permission Create:ScheduleShiftTeam, grup "Jadwal
     * Shift" di /hak-akses) ATAU pengelola penuh jadwal (Update:Schedule). Aksi per regu
     * (edit/hapus) dicek terpisah lewat can('update'/'delete', $team) - lihat
     * openEditTeamModal/saveTeam/deleteTeam. Sengaja BUKAN Update:ShiftScheduleAssignments (tab
     * Detail Penjadwalan) - dipisah supaya bisa digrant beda dari izin isi roster (lihat
     * migration 2026_09_12_110000 kenapa keduanya biasanya sepaket by default). */
    public function getCanManageProperty(): bool
    {
        return Auth::user()->can('update', $this->schedule) || Auth::user()->can('create', ScheduleShiftTeam::class);
    }

    /** Pengelola penuh (Update:Schedule) bebas atur bulan apa pun termasuk yang sudah lewat,
     * Kepala Ruangan cuma bulan berjalan & depan — sama aturan roster (canEditPastDates). */
    public function getCanEditPeriodProperty(): bool
    {
        return Auth::user()->can('update', $this->schedule) || $this->period >= now()->format('Y-m');
    }

    /** Sama persis query getEmployeesProperty di ScheduleShiftRosterTab — karyawan yang
     * muncul di roster jadwal ini (ScheduleAssignment.user_id langsung, bukan lewat jabatan). */
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

    public function getTeamsProperty()
    {
        return ScheduleShiftTeam::where('schedule_id', $this->schedule->id)
            ->orderBy('name')
            ->get();
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
        abort_unless(Auth::user()->can('create', ScheduleShiftTeam::class) || Auth::user()->can('update', $this->schedule), 403);

        $this->editingTeamId = null;
        $this->teamFormName = '';
        $this->teamFormColor = '#042c6c';
        $this->resetErrorBag(['teamFormName', 'teamFormColor']);
        $this->showTeamModal = true;
    }

    /** Buka modal mode "Edit Regu", prefill dari regu yang dipilih. */
    public function openEditTeamModal(string $teamId): void
    {
        $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->findOrFail($teamId);
        abort_unless(Auth::user()->can('update', $team) || Auth::user()->can('update', $this->schedule), 403);

        $this->editingTeamId = $team->id;
        $this->teamFormName = $team->name;
        $this->teamFormColor = $team->color ?? '#042c6c';
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
            abort_unless(Auth::user()->can('create', ScheduleShiftTeam::class) || Auth::user()->can('update', $this->schedule), 403);

            ScheduleShiftTeam::create([
                'schedule_id' => $this->schedule->id,
                'name' => $this->teamFormName,
                'color' => $this->teamFormColor,
                'created_by' => Auth::id(),
            ]);

            Notification::make()->title('Regu berhasil dibuat')->success()->send();
        } else {
            $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->findOrFail($this->editingTeamId);
            abort_unless(Auth::user()->can('update', $team) || Auth::user()->can('update', $this->schedule), 403);

            $team->update(['name' => $this->teamFormName, 'color' => $this->teamFormColor]);

            Notification::make()->title('Regu berhasil diperbarui')->success()->send();
        }

        $this->closeTeamModal();
    }

    public function deleteTeam(string $teamId): void
    {
        $team = ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->findOrFail($teamId);
        abort_unless(Auth::user()->can('delete', $team) || Auth::user()->can('update', $this->schedule), 403);

        // Cascade DB menghapus keanggotaan terkait (schedule_shift_team_members.team_id) -
        // anggota otomatis jadi "Tanpa Regu", bukan error.
        $team->delete();

        Notification::make()->title('Regu berhasil dihapus')->success()->send();
    }

    public function assignMember(string $userId): void
    {
        abort_unless($this->canManage, 403);
        abort_unless($this->canEditPeriod, 403);

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

        Notification::make()->title('Regu ' . $user->name . ' berhasil diperbarui')->success()->send();
    }

    public function render()
    {
        return view('livewire.presensi.schedule-shift-team-tab');
    }
}
