<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleAssignment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman berdiri sendiri "Extra Shift" - versi pertama cuma jalur `assigned` (atasan input
 * langsung, auto approved). Lihat planning/2026-07-31-fitur-extra-shift.md. Tidak ada validasi
 * transisi jam antar-shift (disengaja disederhanakan) dan tidak menyentuh
 * schedule_shift_assignments sama sekali - extra shift berdiri sendiri di tabel terpisah.
 *
 * Karyawan dipilih dulu (dibatasi ke yang jadwalnya tipe Shift), baru dropdown jenis shift
 * kebaca otomatis dari jadwal MILIK karyawan itu sendiri (lewat schedule_assignments) - bukan
 * dari 1 jadwal yang dipilih manual duluan, karena karyawan beda bisa beda schedule.
 */
#[Layout('components.layout', ['title' => 'Extra Shift'])]
class ExtraShiftPage extends Component
{
    public ?string $companyId = null;

    public bool $showAddModal = false;

    public ?string $user_id = null;
    public ?string $covering_user_id = null;
    public ?string $shift_type_id = null;
    public ?string $date = null;
    public ?string $reason = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->date = now()->toDateString();
    }

    public function getCanManageProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value);
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->reset(['user_id', 'covering_user_id', 'shift_type_id']);
    }

    /** Karyawan yang jadwalnya tipe Shift saja - schedule non-shift tidak punya shift_type
     * buat dipilih. */
    public function getEmployeesProperty()
    {
        return ScheduleAssignment::query()
            ->whereNotNull('user_id')
            ->whereHas('schedule', fn ($q) => $q
                ->where('schedule_type', 'shift')
                ->when($this->companyId, fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy('name')
            ->values();
    }

    public function updatedUserId(): void
    {
        $this->shift_type_id = null;
    }

    /** Jenis shift yang bisa diambil - dari jadwal MILIK karyawan yang dipilih di dropdown. */
    public function getShiftTypesProperty()
    {
        if (! $this->user_id) {
            return collect();
        }

        $assignment = ScheduleAssignment::where('user_id', $this->user_id)
            ->whereHas('schedule', fn ($q) => $q->where('schedule_type', 'shift'))
            ->with('schedule.shiftTypes')
            ->first();

        return $assignment?->schedule?->shiftTypes ?? collect();
    }

    public function getExtraShiftsProperty()
    {
        return ExtraShiftAssignment::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->with(['user', 'coveringUser', 'shiftType', 'createdBy'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function openAddModal(): void
    {
        abort_unless($this->canManage, 403);

        $this->reset(['user_id', 'covering_user_id', 'shift_type_id', 'reason']);
        $this->date = now()->toDateString();
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function assign(): void
    {
        abort_unless($this->canManage, 403);

        $data = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'covering_user_id' => ['nullable', 'exists:users,id', 'different:user_id'],
            'shift_type_id' => ['required', 'exists:schedule_shift_types,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($data['user_id']);

        ExtraShiftAssignment::create([
            'company_id' => $user->company_id,
            'user_id' => $data['user_id'],
            'covering_user_id' => $data['covering_user_id'] ?: null,
            'shift_type_id' => $data['shift_type_id'],
            'date' => $data['date'],
            'status' => 'approved',
            'source' => 'assigned',
            'reason' => $data['reason'] ?: null,
            'created_by' => Auth::id(),
        ]);

        $this->reset(['user_id', 'covering_user_id', 'shift_type_id', 'reason']);
        $this->showAddModal = false;

        Notification::make()->title('Extra shift berhasil ditambahkan')->success()->send();
    }

    public function delete(string $id): void
    {
        abort_unless($this->canManage, 403);

        ExtraShiftAssignment::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->findOrFail($id)
            ->delete();

        Notification::make()->title('Extra shift dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.presensi.extra-shift-page');
    }
}
