<?php

namespace App\Livewire\Keuangan;

use App\Enum\Payroll\PayrollComponentEnum;
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
#[Layout('components.layout', ['title' => 'Penggajian Eksekutif'])]
class ExecutiveAllowancesPage extends Component
{
    public bool $showFormModal = false;
    public ?string $editingId = null;
    public ?string $formUserId = null;
    /** 'component' (pilih dari PayrollComponentEnum) atau 'manual' (label bebas) - lihat saveForm().
     * Default 'component' (2026-08-12, instruksi user) - baris eksekutif kebanyakan memang
     * komponen gaji baku (Gaji Pokok, dst), Manual cuma buat kasus di luar itu. */
    public string $formSource = 'component';
    public ?string $formComponent = null;
    public ?string $formType = null;
    public ?string $formLabel = null;
    public ?string $formAmount = null;
    public ?string $formNote = null;

    /** @return array<string, string> */
    public function getComponentOptionsProperty(): array
    {
        return collect(PayrollComponentEnum::cases())
            ->mapWithKeys(fn (PayrollComponentEnum $case) => [$case->value => $case->label()])
            ->all();
    }

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
        $this->formSource = 'component';
        $this->formComponent = null;
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
        $this->formSource = $allowance->component ? 'component' : 'manual';
        $this->formComponent = $allowance->component;
        $this->formType = $allowance->type;
        $this->formLabel = $allowance->label;
        $this->formAmount = (string) $allowance->amount;
        $this->formNote = $allowance->note;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    /** wire:model.live="formComponent" - komponen dipilih, turunkan label/type otomatis dari enum. */
    public function updatedFormComponent(): void
    {
        if ($this->formSource !== 'component' || ! $this->formComponent) {
            return;
        }

        $component = PayrollComponentEnum::tryFrom($this->formComponent);

        if (! $component) {
            return;
        }

        $this->formLabel = $component->label();
        $this->formType = $component->type()->value;
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
            'formSource' => 'required|in:component,manual',
            'formComponent' => 'required_if:formSource,component|nullable|in:' . implode(',', array_column(PayrollComponentEnum::cases(), 'value')),
            'formType' => 'required_if:formSource,manual|in:earning,deduction',
            'formLabel' => 'required_if:formSource,manual|nullable|string|max:255',
            'formAmount' => 'required|numeric|min:0.01',
            'formNote' => 'nullable|string',
        ], [], [
            'formUserId' => 'penerima',
            'formComponent' => 'komponen',
            'formType' => 'jenis',
            'formLabel' => 'label',
            'formAmount' => 'nominal',
            'formNote' => 'catatan',
        ]);

        // Komponen: label/type SELALU diturunkan dari enum saat simpan (bukan cuma cache
        // updatedFormComponent()) - antisipasi race/lupa trigger live update.
        if ($this->formSource === 'component') {
            $component = PayrollComponentEnum::from($this->formComponent);
            $this->formLabel = $component->label();
            $this->formType = $component->type()->value;
        }

        $data = [
            'user_id' => $this->formUserId,
            'component' => $this->formSource === 'component' ? $this->formComponent : null,
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
