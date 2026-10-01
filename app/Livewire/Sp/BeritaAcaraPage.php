<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Sp\SpReport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar Berita Acara alur baru (instruksi user 2026-10-01) - sisi HC, padanan SpPage. BA lama
 * (status NULL, bagian dari SP) tidak ikut: itu tetap diakses lewat detail SP-nya.
 */
#[Layout('components.layout', ['title' => 'Berita Acara'])]
class BeritaAcaraPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('ViewAny:EmployeeSp'), 403);

        if (! $this->canBrowseAllCompanies || $this->companyId === null) {
            $this->companyId = $user->company_id;
        }
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function updatedCompanyId(): void
    {
        if (! $this->canBrowseAllCompanies) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('Create:EmployeeSp');
    }

    public function getReportsProperty()
    {
        $search = trim($this->search);

        return SpReport::query()
            ->whereNotNull('status')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with('user')
            ->latest()
            ->paginate(10)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search]);
    }

    public function render()
    {
        return view('livewire.sp.berita-acara-page');
    }
}
