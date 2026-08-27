<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollComponentOverride;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Override Komponen Gaji" (2026-08-26, instruksi user) — override manual per
 * karyawan+komponen APA PUN (bukan cuma Koperasi/Bon), Calculator komponen itu di-skip total
 * kalau ada baris aktif di sini (lihat PayrollService::runComponent()). Pola sama KoperasiPage
 * (company filter+search, modal tambah/edit, PayrollEncrypted cast wajib per-instance save).
 */
#[Layout('components.layout', ['title' => 'Gaji Khusus'])]
class PayrollComponentOverridesPage extends Component
{
    public ?string $companyId = null;
    public string $search = '';

    public bool $showAddModal = false;
    public ?string $addUserId = null;
    public ?string $addComponent = null;
    public ?string $addAmount = null;
    public ?string $addNote = null;

    public ?string $editingId = null;
    public ?string $editAmount = null;
    public ?string $editNote = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_OVERRIDE_KOMPONEN_GAJI->value), 403);

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

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_OVERRIDE_KOMPONEN_GAJI->value);
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getComponentOptionsProperty(): array
    {
        return collect(PayrollComponentEnum::cases())
            ->mapWithKeys(fn (PayrollComponentEnum $c) => [$c->value => $c->label()])
            ->all();
    }

    public function getOverridesProperty()
    {
        $search = trim($this->search);

        return PayrollComponentOverride::query()
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId)
                ->when($search !== '', fn ($q2) => $q2->where(fn ($q3) => $q3
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"))))
            ->with('user')
            ->latest()
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
            ->orderBy('name')
            ->get();
    }

    public function openAddModal(): void
    {
        abort_unless($this->canEdit, 403);

        $this->addUserId = null;
        $this->addComponent = null;
        $this->addAmount = null;
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
        abort_unless($this->canEdit, 403);

        $this->validate([
            'addUserId' => 'required|exists:users,id',
            'addComponent' => ['required', \Illuminate\Validation\Rule::in(array_map(fn ($c) => $c->value, PayrollComponentEnum::cases()))],
            'addAmount' => 'required|numeric|min:0',
        ], [], [
            'addUserId' => 'karyawan',
            'addComponent' => 'komponen',
            'addAmount' => 'nominal',
        ]);

        abort_if(
            PayrollComponentOverride::where('user_id', $this->addUserId)->where('component', $this->addComponent)->exists(),
            409,
            'Karyawan ini sudah punya Gaji Khusus untuk komponen ini — ubah nominalnya dari daftar, bukan tambah lagi.'
        );

        try {
            PayrollComponentOverride::create([
                'user_id' => $this->addUserId,
                'component' => $this->addComponent,
                'amount' => $this->addAmount,
                'note' => $this->addNote,
                'is_active' => true,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            abort(409, 'Karyawan ini sudah punya Gaji Khusus untuk komponen ini — coba muat ulang halaman.');
        }

        $this->showAddModal = false;

        Notification::make()
            ->title('Gaji Khusus berhasil ditambahkan')
            ->body('Akan dipakai mulai Generate/Regenerate berikutnya, menggantikan hasil hitungan Calculator komponen ini.')
            ->success()
            ->send();
    }

    public function openEditModal(string $id): void
    {
        abort_unless($this->canEdit, 403);

        $override = PayrollComponentOverride::findOrFail($id);

        $this->editingId = $id;
        $this->editAmount = (string) $override->amount;
        $this->editNote = $override->note;
        $this->resetErrorBag();
    }

    public function closeEditModal(): void
    {
        $this->editingId = null;
    }

    public function saveEdit(): void
    {
        abort_unless($this->canEdit, 403);

        $this->validate([
            'editAmount' => 'required|numeric|min:0',
        ], [], [
            'editAmount' => 'nominal',
        ]);

        // Instance save (bukan mass update()) - 'amount' di-cast PayrollEncrypted, sama gotcha
        // yang didokumentasikan di KoperasiPage::saveEdit().
        $override = PayrollComponentOverride::findOrFail($this->editingId);
        $override->update(['amount' => $this->editAmount, 'note' => $this->editNote]);

        $this->editingId = null;

        Notification::make()->title('Gaji Khusus berhasil diubah')->success()->send();
    }

    public function toggleActive(string $id): void
    {
        abort_unless($this->canEdit, 403);

        $override = PayrollComponentOverride::findOrFail($id);
        $override->update(['is_active' => ! $override->is_active]);
    }

    public function removeOverride(string $id): void
    {
        abort_unless($this->canEdit, 403);

        PayrollComponentOverride::findOrFail($id)->delete();

        Notification::make()->title('Gaji Khusus dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-component-overrides-page');
    }
}
