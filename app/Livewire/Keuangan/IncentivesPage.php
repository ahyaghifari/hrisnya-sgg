<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Versi portal dari IncentiveResource (/admin/incentives) — custom, List.js buat cari+
 * pagination (pola sama PayrollPeriodsPage/KoperasiPage), BUKAN lagi reuse Table/Form
 * Filament. Otorisasi Create/Update/Delete:Incentive dicek manual (lihat IncentivePolicy)
 * supaya guard yang sebelumnya nempel di Filament Action tidak ikut hilang pas direplace.
 */
#[Layout('components.layout', ['title' => 'Insentif'])]
class IncentivesPage extends Component
{
    public ?string $companyId = null;

    public bool $showFormModal = false;
    public ?string $editingId = null;
    public ?string $formUserId = null;
    public ?string $formName = null;
    public ?string $formDate = null;
    public ?string $formAmount = null;
    public ?string $formNote = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    public function updatedCompanyId(): void
    {
        $this->dispatch('list-table:refresh');
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getIncentivesProperty()
    {
        return Incentive::query()
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->with('user.company')
            ->orderByDesc('incentive_date')
            ->get();
    }

    public function getEmployeesProperty()
    {
        return $this->companyId
            ? User::where('company_id', $this->companyId)->whereHas('profile')->orderBy('name')->get()
            : collect();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('create', Incentive::class);
    }

    // ── Form Tambah/Edit ─────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', Incentive::class), 403);

        $this->editingId = null;
        $this->formUserId = null;
        $this->formName = null;
        $this->formDate = null;
        $this->formAmount = null;
        $this->formNote = null;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $incentive = Incentive::findOrFail($id);

        abort_unless(Auth::user()->can('update', $incentive), 403);
        abort_if($incentive->is_processed, 403, 'Insentif yang sudah diproses tidak bisa diedit.');

        $this->editingId = $incentive->id;
        $this->formUserId = $incentive->user_id;
        $this->formName = $incentive->name;
        $this->formDate = optional($incentive->incentive_date)->format('Y-m-d');
        $this->formAmount = (string) $incentive->amount;
        $this->formNote = $incentive->note;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function saveForm(): void
    {
        $ability = $this->editingId ? 'update' : 'create';
        $subject = $this->editingId ? Incentive::findOrFail($this->editingId) : Incentive::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $this->validate([
            'formUserId' => 'required|exists:users,id',
            'formName' => 'required|string|max:255',
            'formDate' => 'required|date',
            'formAmount' => 'required|numeric|min:0.01',
            'formNote' => 'nullable|string',
        ], [], [
            'formUserId' => 'penerima',
            'formName' => 'nama program',
            'formDate' => 'tanggal',
            'formAmount' => 'nominal',
            'formNote' => 'catatan',
        ]);

        $data = [
            'user_id' => $this->formUserId,
            'name' => $this->formName,
            'incentive_date' => $this->formDate,
            'amount' => $this->formAmount,
            'note' => $this->formNote,
        ];

        if ($this->editingId) {
            Incentive::whereKey($this->editingId)->update($data);
        } else {
            Incentive::create($data);
        }

        $this->showFormModal = false;
        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title($this->editingId ? 'Insentif berhasil diubah' : 'Insentif baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function removeIncentive(string $id): void
    {
        $incentive = Incentive::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $incentive), 403);
        abort_if($incentive->is_processed, 403, 'Insentif yang sudah diproses tidak bisa dihapus.');

        $incentive->delete();
        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title('Insentif berhasil dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.incentives-page');
    }
}
