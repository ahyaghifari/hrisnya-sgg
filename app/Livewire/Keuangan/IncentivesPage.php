<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Setting;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Versi portal dari IncentiveResource (/admin/incentives) — custom Livewire page, BUKAN lagi
 * reuse Table/Form Filament. Cari/company/status lewat Livewire (server-side) — SEBELUMNYA
 * cari+filter pakai List.js di browser, tapi List.js mengunci baris tbody-nya sendiri sehingga
 * tidak ikut ke-morph Livewire tiap company ganti (baris lama nyangkut terus), jadi dicabut.
 * Otorisasi Create/Update/Delete:Incentive dicek manual (lihat IncentivePolicy) supaya guard
 * yang sebelumnya nempel di Filament Action tidak ikut hilang pas direplace.
 */
#[Layout('components.layout', ['title' => 'Insentif'])]
class IncentivesPage extends Component
{
    public ?string $companyId = null;
    public string $search = '';

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

    /** Halaman ini cuma buat company metode "master" — company "import" pakai halaman Payroll > Import Insentif. */
    public function getInsentifMetodeProperty(): string
    {
        return (string) Setting::resolve('insentif_metode', $this->companyId, default: 'master');
    }

    /** Komponen gaji Insentif harus diaktifkan dulu di halaman Komponen Gaji sebelum bisa dipakai di sini. */
    public function getInsentifActiveProperty(): bool
    {
        return CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->where('component', PayrollComponentEnum::INSENTIF->value)
            ->where('is_active', true)
            ->exists();
    }

    public function getIncentivesProperty()
    {
        $search = trim($this->search);

        return Incentive::query()
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
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
            'formAmount' => 'required|numeric|min:0.01',
            'formName' => 'nullable|string|max:255',
            'formDate' => 'nullable|date',
            'formNote' => 'nullable|string',
        ], [], [
            'formUserId' => 'penerima',
            'formAmount' => 'nominal',
            'formName' => 'nama program',
            'formDate' => 'tanggal',
            'formNote' => 'catatan',
        ]);

        $data = [
            'user_id' => $this->formUserId,
            'name' => $this->formName,
            // Tanggal dipakai IncentiveCalculator buat nentuin insentif ini masuk periode
            // payroll yang mana — kalau admin tidak isi, default ke hari ini (bukan dibiarkan
            // null) supaya tetap ke-generate di periode berjalan.
            'incentive_date' => $this->formDate ?: now()->toDateString(),
            'amount' => $this->formAmount,
            'note' => $this->formNote,
        ];

        if ($this->editingId) {
            Incentive::whereKey($this->editingId)->update($data);
        } else {
            Incentive::create($data);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Insentif berhasil diubah' : 'Insentif baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function removeIncentive(string $id): void
    {
        $incentive = Incentive::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $incentive), 403);

        $incentive->delete();

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
