<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\ResolvesShiftTeamMembers;
use App\Livewire\Presensi\Concerns\ResolvesExtraShiftOptions;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftTeamMember;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Modal "Extra Shift" instan nempel di widget "Jadwal Shift Tim" (Dashboard) - buat atasan
 * berjadwal shift (mis. Kepala Ruangan) assign extra shift ke bawahannya sendiri tanpa lewat
 * approval, pola sama ExtraShiftPage (halaman admin berdiri sendiri, company-wide) tapi
 * scope karyawannya dipersempit ke tim (ResolvesShiftTeamMembers) + diri sendiri.
 *
 * Alur (dirombak 2026-08-19, lihat ResolvesExtraShiftOptions) — Extra Shift BUKAN Tukar Shift,
 * cuma berpengaruh ke SATU karyawan: pilih karyawan -> pilih tanggal -> sistem tampilkan shift
 * NORMAL karyawan itu di tanggal itu -> pilih shift tambahan dari daftar yang diizinkan
 * `extra_shift_rules` (pasangan from/to per company). Selalu source=assigned/status=approved -
 * jalur "requested" (pengajuan + approval) belum ada di manapun.
 *
 * Role/permission (Create:ExtraShiftAssignment, Shield) belum di-assign ke role manapun saat
 * fitur ini dibuat - user yang akan menyiapkan sendiri lewat /hak-akses. Tombol pembuka di
 * home-cards.blade.php sudah gerbang Dashboard::canCreateExtraShift, method di sini abort_unless
 * ulang (defense-in-depth kalau method dipanggil langsung).
 */
class ExtraShiftRequestModal extends Component
{
    use ResolvesShiftTeamMembers;
    use ResolvesExtraShiftOptions;

    public bool $showModal = false;

    public ?string $user_id = null;
    public ?string $date = null;
    public ?string $shift_type_id = null;
    public ?string $reason = null;

    public function openModal(): void
    {
        abort_unless(Auth::user()->can('create', ExtraShiftAssignment::class), 403);

        $this->reset(['user_id', 'date', 'shift_type_id', 'reason']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    /** Tim (lihat ResolvesShiftTeamMembers) + diri sendiri, yang beneran berjadwal shift - beda
     * dari TeamShiftScheduleCard (widget lihat-lihat, semua bawahan tampil apa adanya), di sini
     * TETAP difilter hasShiftSchedule() karena yang dipilih harus punya jadwal shift nyata biar
     * ada basis shift normal buat aturan Extra Shift. */
    public function getEligibleEmployeesProperty()
    {
        $user = Auth::user();

        return $this->subordinatesWithSharedSchedule($user)
            ->push($user)
            ->unique('id')
            ->filter(fn (User $u) => $u->hasShiftSchedule())
            ->sortBy('name')
            ->values();
    }

    public function updatedUserId(): void
    {
        $this->reset(['date', 'shift_type_id']);
    }

    public function updatedDate(): void
    {
        $this->shift_type_id = null;
    }

    /** @return array{category: string, label: string}|null */
    public function getNormalShiftProperty(): ?array
    {
        if (! $this->user_id || ! $this->date) {
            return null;
        }

        $user = User::find($this->user_id);

        return $user ? $this->resolveNormalShift($user, $this->date) : null;
    }

    public function getShiftTypeOptionsProperty()
    {
        if (! $this->user_id || ! $this->date) {
            return collect();
        }

        $user = User::find($this->user_id);

        return $user ? $this->resolveAllowedExtraShiftTypes($user, $this->date) : collect();
    }

    /** Nama Regu karyawan di bulan tanggal terpilih - null kalau jadwalnya tidak pakai Regu atau
     * karyawan belum masuk regu manapun (shift tambahan tidak dibatasi regu). */
    public function getTeamNameProperty(): ?string
    {
        $normal = $this->normalShift;

        if (! $normal || ! $this->user_id) {
            return null;
        }

        $schedule = Schedule::find($normal['scheduleId']);

        if (! $schedule?->use_teams) {
            return null;
        }

        return ScheduleShiftTeamMember::forPeriod($schedule->id, $this->user_id, Carbon::parse($this->date)->format('Y-m'))?->team?->name;
    }

    /** Beda pesan dari "belum ada aturan" - karyawan ini SUDAH punya extra shift lain di
     * tanggal itu, bukan soal rule-nya kosong. */
    public function getAlreadyHasExtraShiftProperty(): bool
    {
        if (! $this->user_id || ! $this->date) {
            return false;
        }

        $user = User::find($this->user_id);

        return $user ? $this->hasExtraShiftOnDate($user, $this->date) : false;
    }

    public function submit(): void
    {
        abort_unless(Auth::user()->can('create', ExtraShiftAssignment::class), 403);

        $data = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'shift_type_id' => ['required', 'exists:schedule_shift_types,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($data['user_id']);

        $allowed = $this->resolveAllowedExtraShiftTypes($user, $data['date']);
        abort_unless($allowed->contains('id', $data['shift_type_id']), 422, 'Shift yang dipilih tidak sesuai aturan Extra Shift untuk shift normal karyawan ini di tanggal itu.');

        ExtraShiftAssignment::create([
            'company_id' => $user->company_id,
            'user_id' => $data['user_id'],
            'shift_type_id' => $data['shift_type_id'],
            'date' => $data['date'],
            'status' => 'approved',
            'source' => 'assigned',
            'reason' => $data['reason'] ?: null,
            'created_by' => Auth::id(),
        ]);

        Notification::make()->title('Extra shift berhasil ditambahkan')->success()->send();

        $this->showModal = false;
        $this->reset(['user_id', 'date', 'shift_type_id', 'reason']);
    }

    public function render()
    {
        return view('livewire.presensi.extra-shift-request-modal');
    }
}
