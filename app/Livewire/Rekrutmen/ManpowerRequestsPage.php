<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Models\Job\ManpowerRequests;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * List Permintaan Tenaga Kerja versi portal — padanan ManpowerRequestResource admin. Nav kiri
 * "Kuota" vs "Perubahan Karyawan" (query param `?type=`) — pola sama seperti
 * ListManpowerRequests admin (badge diambil dari getNavigationItems()).
 */
#[Layout('components.layout', ['title' => 'Permintaan Tenaga Kerja'])]
class ManpowerRequestsPage extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $tab = 'quota';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:ManpowerRequests'), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $tab = request()->query('tab', 'quota');
        $this->tab = in_array($tab, ['quota', 'employee_change'], true) ? $tab : 'quota';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
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

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('Create:ManpowerRequests');
    }

    public function getQuotaCountProperty(): int
    {
        return ManpowerRequests::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->where('type', ManpowerRequestType::ADDITIONAL_QUOTA->value)
            ->where('status', JobPositionReqStatus::REQUESTED->value)
            ->count();
    }

    public function getEmployeeChangeCountProperty(): int
    {
        return ManpowerRequests::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->whereIn('type', [ManpowerRequestType::ADDITIONAL_EMPLOYEE->value, ManpowerRequestType::EMPLOYEE_REPLACEMENT->value])
            ->where('status', JobPositionReqStatus::REQUESTED->value)
            ->count();
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        $typeFilter = $this->tab === 'quota'
            ? [ManpowerRequestType::ADDITIONAL_QUOTA->value]
            : [ManpowerRequestType::ADDITIONAL_EMPLOYEE->value, ManpowerRequestType::EMPLOYEE_REPLACEMENT->value];

        return ManpowerRequests::query()
            ->whereIn('type', $typeFilter)
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($search !== '', fn ($q) => $q->whereHas('jobPosition', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with(['jobPosition.orgStructure', 'company']);
    }

    public function getRequestsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10);
    }

    public function render()
    {
        return view('livewire.rekrutmen.manpower-requests-page');
    }
}
