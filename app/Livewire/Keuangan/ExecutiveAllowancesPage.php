<?php

namespace App\Livewire\Keuangan;

use App\Models\Payroll\ExecutiveAllowance;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Roster pendapatan/potongan manual khusus User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS (Presiden
 * Direktur & Sekretaris Direktur) - lihat ExecutiveAllowanceCalculator. Cuma 2 orang total jadi
 * tanpa filter company/search, beda dari IncentivesPage yang jadi contoh strukturnya.
 */
#[Layout('components.layout', ['title' => 'Tunjangan & Potongan Eksekutif'])]
class ExecutiveAllowancesPage extends Component
{
    public bool $showFormModal = false;
    public ?string $editingId = null;
    public ?string $formUserId = null;
    public ?string $formType = null;
    public ?string $formLabel = null;
    public ?string $formAmount = null;
    public ?string $formNote = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', ExecutiveAllowance::class), 403);
    }

    public function getAllowancesProperty()
    {
        return ExecutiveAllowance::query()
            ->whereIn('user_id', User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getEmployeesProperty()
    {
        return User::withoutGlobalScope('non_karyawan')
            ->whereIn('id', User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS)
            ->orderBy('name')
            ->get();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('create', ExecutiveAllowance::class);
    }

    // ── Form Tambah/Edit ─────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', ExecutiveAllowance::class), 403);

        $this->editingId = null;
        $this->formUserId = null;
        $this->formType = 'earning';
        $this->formLabel = null;
        $this->formAmount = null;
        $this->formNote = null;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $allowance = ExecutiveAllowance::findOrFail($id);

        abort_unless(Auth::user()->can('update', $allowance), 403);

        $this->editingId = $allowance->id;
        $this->formUserId = $allowance->user_id;
        $this->formType = $allowance->type;
        $this->formLabel = $allowance->label;
        $this->formAmount = (string) $allowance->amount;
        $this->formNote = $allowance->note;
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
        $subject = $this->editingId ? ExecutiveAllowance::findOrFail($this->editingId) : ExecutiveAllowance::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $this->validate([
            'formUserId' => 'required|in:' . implode(',', User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS),
            'formType' => 'required|in:earning,deduction',
            'formLabel' => 'required|string|max:255',
            'formAmount' => 'required|numeric|min:0.01',
            'formNote' => 'nullable|string',
        ], [], [
            'formUserId' => 'penerima',
            'formType' => 'jenis',
            'formLabel' => 'label',
            'formAmount' => 'nominal',
            'formNote' => 'catatan',
        ]);

        $data = [
            'user_id' => $this->formUserId,
            'type' => $this->formType,
            'label' => $this->formLabel,
            'amount' => $this->formAmount,
            'note' => $this->formNote,
        ];

        if ($this->editingId) {
            ExecutiveAllowance::whereKey($this->editingId)->update($data);
        } else {
            ExecutiveAllowance::create([...$data, 'is_active' => true]);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Baris berhasil diubah' : 'Baris baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function toggleActive(string $id): void
    {
        $allowance = ExecutiveAllowance::findOrFail($id);

        abort_unless(Auth::user()->can('update', $allowance), 403);

        $allowance->update(['is_active' => ! $allowance->is_active]);
    }

    public function removeAllowance(string $id): void
    {
        $allowance = ExecutiveAllowance::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $allowance), 403);

        $allowance->delete();

        Notification::make()
            ->title('Baris berhasil dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.executive-allowances-page');
    }
}
