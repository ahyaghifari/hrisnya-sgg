<?php

namespace App\Livewire\Keuangan;

use App\Models\Master\Companies;
use App\Models\Payroll\BpjsDependent;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Roster tanggungan BPJS Kesehatan — 1 baris per karyawan (unique), BpjsKesehatanCalculator
 * mengalikan potongan dengan (1 + count). Pola sama KoperasiPage.
 */
#[Layout('components.layout', ['title' => 'Tanggungan BPJS'])]
class BpjsDependentsPage extends Component
{
    public ?string $companyId = null;
    public string $search = '';

    public bool $showAddModal = false;
    public ?string $addUserId = null;
    public ?string $addCount = null;
    public ?string $addNote = null;

    public bool $showEditModal = false;
    public ?string $editingId = null;
    public ?string $editCount = null;
    public ?string $editNote = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', BpjsDependent::class), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getDependentsProperty()
    {
        $search = trim($this->search);

        return BpjsDependent::query()
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId)
                ->when($search !== '', fn ($q2) => $q2->where(fn ($q3) => $q3
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"))))
            ->with('user')
            ->get();
    }

    public function getAvailableUsersProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->whereDoesntHave('bpjsDependent')
            ->orderBy('name')
            ->get();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('create', BpjsDependent::class);
    }

    public function getCanUpdateProperty(): bool
    {
        return Auth::user()->can('update', BpjsDependent::class);
    }

    // ── Tambah ───────────────────────────────────────────────────────────────

    public function openAddModal(): void
    {
        abort_unless(Auth::user()->can('create', BpjsDependent::class), 403);

        $this->addUserId = null;
        $this->addCount = null;
        $this->addNote = null;
        $this->resetErrorBag();
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function saveAdd(): void
    {
        abort_unless(Auth::user()->can('create', BpjsDependent::class), 403);

        $this->validate([
            'addUserId' => 'required|exists:users,id',
            'addCount' => 'required|integer|min:1',
            'addNote' => 'nullable|string|max:255',
        ], [], [
            'addUserId' => 'karyawan',
            'addCount' => 'jumlah tanggungan',
            'addNote' => 'keterangan',
        ]);

        abort_if(
            BpjsDependent::where('user_id', $this->addUserId)->exists(),
            409,
            'Karyawan ini sudah punya data tanggungan — ubah dari daftar, bukan tambah lagi.'
        );

        try {
            BpjsDependent::create([
                'user_id' => $this->addUserId,
                'count' => $this->addCount,
                'note' => $this->addNote,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Karyawan ini sudah punya data tanggungan — coba muat ulang halaman.');
        }

        $this->showAddModal = false;

        Notification::make()
            ->title('Tanggungan BPJS berhasil ditambahkan')
            ->body('Akan ikut ke payroll mulai Generate/Regenerate berikutnya.')
            ->success()
            ->send();
    }

    // ── Edit ─────────────────────────────────────────────────────────────────

    public function openEditModal(string $id): void
    {
        $dependent = BpjsDependent::findOrFail($id);

        abort_unless(Auth::user()->can('update', $dependent), 403);

        $this->editingId = $dependent->id;
        $this->editCount = (string) $dependent->count;
        $this->editNote = $dependent->note;
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
    }

    public function saveEdit(): void
    {
        $dependent = BpjsDependent::findOrFail($this->editingId);

        abort_unless(Auth::user()->can('update', $dependent), 403);

        $this->validate([
            'editCount' => 'required|integer|min:1',
            'editNote' => 'nullable|string|max:255',
        ], [], [
            'editCount' => 'jumlah tanggungan',
            'editNote' => 'keterangan',
        ]);

        $dependent->update([
            'count' => $this->editCount,
            'note' => $this->editNote,
        ]);

        $this->showEditModal = false;

        Notification::make()
            ->title('Tanggungan BPJS berhasil diubah')
            ->success()
            ->send();
    }

    // ── Aktif/Nonaktif & Hapus ───────────────────────────────────────────────

    public function toggleActive(string $id): void
    {
        $dependent = BpjsDependent::findOrFail($id);

        abort_unless(Auth::user()->can('update', $dependent), 403);

        $dependent->update(['is_active' => ! $dependent->is_active]);
    }

    public function removeDependent(string $id): void
    {
        $dependent = BpjsDependent::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $dependent), 403);

        $dependent->delete();

        Notification::make()
            ->title('Tanggungan BPJS berhasil dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.bpjs-dependents-page');
    }
}
