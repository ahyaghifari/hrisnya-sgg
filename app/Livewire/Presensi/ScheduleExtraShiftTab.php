<?php

namespace App\Livewire\Presensi;

use App\Livewire\Presensi\Concerns\ResolvesExtraShiftOptions;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Tab "Extra Shift" di ScheduleDetailPage - daftar + tombol tambah Extra Shift SCOPED ke
 * Schedule ini saja (beda dari ExtraShiftPage yang company-wide, dan ExtraShiftRequestModal
 * yang scoped ke tim atasan lewat ResolvesShiftTeamMembers) - dipakai kalau mau kelola extra
 * shift langsung dari konteks 1 jadwal tertentu, sama seperti tab Template Shift/Detail
 * Penjadwalan. Alur & validasi form-nya sama persis surface lain (lihat
 * ResolvesExtraShiftOptions): pilih karyawan -> tanggal -> shift normal ditampilkan -> pilih
 * shift tambahan dari extra_shift_rules. Permission Shield sama (Create/Delete:ExtraShiftAssignment)
 * - bukan KELOLA_JADWAL_SHIFT, karena resource-nya tetap ExtraShiftAssignment, cuma jalur
 * masuknya yang beda.
 */
class ScheduleExtraShiftTab extends Component
{
    use ResolvesExtraShiftOptions;

    public Schedule $schedule;

    public bool $showAddModal = false;
    public ?string $user_id = null;
    public ?string $date = null;
    public ?string $shift_type_id = null;
    public ?string $reason = null;

    public function mount(Schedule $schedule): void
    {
        abort_unless(Auth::user()->can('viewAny', ExtraShiftAssignment::class), 403);

        $this->schedule = $schedule;
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('create', ExtraShiftAssignment::class);
    }

    public function getCanDeleteProperty(): bool
    {
        return Auth::user()->can('Delete:ExtraShiftAssignment');
    }

    /** Karyawan yang di-assign ke Schedule ini - sama scoping-nya dengan tab Template
     *  Shift/Detail Penjadwalan (ScheduleAssignment.schedule_id), bukan company-wide. */
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

    public function getExtraShiftsProperty()
    {
        return ExtraShiftAssignment::query()
            ->whereHas('shiftType', fn ($q) => $q->where('schedule_id', $this->schedule->id))
            ->with(['user', 'shiftType', 'createdBy'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();
    }

    public function openAddModal(): void
    {
        abort_unless($this->canCreate, 403);

        $this->reset(['user_id', 'date', 'shift_type_id', 'reason']);
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function assign(): void
    {
        abort_unless($this->canCreate, 403);

        $data = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date', 'after_or_equal:yesterday'],
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

        $this->reset(['user_id', 'date', 'shift_type_id', 'reason']);
        $this->showAddModal = false;

        Notification::make()->title('Extra shift berhasil ditambahkan')->success()->send();
    }

    public function delete(string $id): void
    {
        $extraShift = ExtraShiftAssignment::query()
            ->whereHas('shiftType', fn ($q) => $q->where('schedule_id', $this->schedule->id))
            ->findOrFail($id);

        abort_unless(Auth::user()->can('delete', $extraShift), 403);

        // Tanggal yang sudah lewat cuma bisa dihapus dari halaman Presensi Absensi
        // (ExtraShiftPage), bukan dari tab ini - lihat catatan blade.
        abort_if(
            \Carbon\Carbon::parse($extraShift->date)->lt(now()->startOfDay()),
            403,
            'Extra shift yang tanggalnya sudah lewat tidak bisa dihapus dari sini, hapus lewat halaman Presensi Absensi > Extra Shift.'
        );

        $extraShift->delete();

        Notification::make()->title('Extra shift dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.presensi.schedule-extra-shift-tab');
    }
}
