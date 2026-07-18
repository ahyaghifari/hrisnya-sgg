<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Roster anggota koperasi (portal, custom List.js — pola sama PayrollPeriodsPage) — sumber
 * yang otomatis di-sync ke payroll_manual_lines tiap Generate Payroll, lihat
 * PayrollService::syncKoperasiManualLines().
 */
#[Layout('components.layout', ['title' => 'Koperasi'])]
class KoperasiPage extends Component
{
    public ?string $companyId = null;

    public array $selected = [];

    public bool $showAddModal = false;
    public ?string $addUserId = null;
    public ?string $addAmount = null;

    public bool $showEditModal = false;
    public array $editingIds = [];
    public ?string $editAmount = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    public function updatedCompanyId(): void
    {
        $this->selected = [];
        $this->dispatch('list-table:refresh');
    }

    public function toggleSelectAll(bool $checked): void
    {
        $this->selected = $checked ? $this->members->pluck('id')->all() : [];
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getMembersProperty()
    {
        return UserKoperasi::query()
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId))
            ->with('user')
            ->get();
    }

    /** Karyawan aktif di company terpilih yang belum jadi anggota koperasi. */
    public function getAvailableUsersProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->whereDoesntHave('koperasi')
            ->orderBy('name')
            ->get();
    }

    // ── Tambah Anggota ──────────────────────────────────────────────────────

    public function openAddModal(): void
    {
        $this->addUserId = null;
        $this->addAmount = null;
        $this->resetErrorBag();
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function saveAdd(): void
    {
        $this->validate([
            'addUserId' => 'required|exists:users,id',
            'addAmount' => 'required|numeric|min:0.01',
        ], [], [
            'addUserId' => 'karyawan',
            'addAmount' => 'nominal',
        ]);

        UserKoperasi::updateOrCreate(
            ['user_id' => $this->addUserId],
            ['amount' => $this->addAmount, 'is_active' => true]
        );

        $this->showAddModal = false;
        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title('Anggota koperasi berhasil ditambahkan')
            ->body('Akan ikut ke payroll mulai Generate/Regenerate berikutnya.')
            ->success()
            ->send();
    }

    // ── Edit Amount (satu atau beberapa baris yang dicentang) ──────────────

    public function openEditModal(?string $id = null): void
    {
        $this->editingIds = $id ? [$id] : $this->selected;

        if ($this->editingIds === []) {
            return;
        }

        $this->editAmount = null;
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editAmount' => 'required|numeric|min:0.01',
        ], [], [
            'editAmount' => 'nominal',
        ]);

        UserKoperasi::whereIn('id', $this->editingIds)->update(['amount' => $this->editAmount]);

        $this->showEditModal = false;
        $this->selected = [];
        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title('Amount berhasil diubah')
            ->body(count($this->editingIds) . ' anggota diperbarui.')
            ->success()
            ->send();
    }

    // ── Aktif/Nonaktif & Hapus ───────────────────────────────────────────────

    public function toggleActive(string $id): void
    {
        $member = UserKoperasi::findOrFail($id);
        $member->update(['is_active' => ! $member->is_active]);

        $this->dispatch('list-table:refresh');
    }

    public function removeMember(string $id): void
    {
        UserKoperasi::whereKey($id)->delete();
        $this->selected = array_values(array_diff($this->selected, [$id]));

        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title('Anggota koperasi dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.koperasi-page');
    }
}
