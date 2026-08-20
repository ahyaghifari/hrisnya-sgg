<?php

namespace App\Livewire\Presensi;

use App\Livewire\Presensi\Concerns\ResolvesExtraShiftOptions;
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
 * langsung, auto approved). Lihat planning/2026-07-31-fitur-extra-shift.md.
 *
 * Alur (dirombak 2026-08-19, lihat ResolvesExtraShiftOptions) — Extra Shift BUKAN Tukar Shift,
 * cuma berpengaruh ke SATU karyawan: pilih karyawan -> pilih tanggal -> sistem tampilkan shift
 * NORMAL karyawan itu di tanggal itu -> pilih shift tambahan dari daftar yang diizinkan
 * `extra_shift_rules` (pasangan from/to per company, dikelola di ExtraShiftRulePage).
 */
#[Layout('components.layout', ['title' => 'Extra Shift'])]
class ExtraShiftPage extends Component
{
    use ResolvesExtraShiftOptions;

    public ?string $companyId = null;

    public bool $showAddModal = false;

    public ?string $user_id = null;
    public ?string $date = null;
    public ?string $shift_type_id = null;
    public ?string $reason = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', ExtraShiftAssignment::class), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('create', ExtraShiftAssignment::class);
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

        $this->reset(['user_id', 'date', 'shift_type_id']);
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
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->with(['user', 'shiftType', 'createdBy'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(50)
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
            'date' => ['required', 'date'],
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
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->findOrFail($id);

        abort_unless(Auth::user()->can('delete', $extraShift), 403);

        $extraShift->delete();

        Notification::make()->title('Extra shift dihapus')->success()->send();
    }

    public function getCanDeleteProperty(): bool
    {
        return Auth::user()->can('Delete:ExtraShiftAssignment');
    }

    public function render()
    {
        return view('livewire.presensi.extra-shift-page');
    }
}
