<?php

namespace App\Livewire\Rekrutmen;

use App\Models\Job\JobOpenings;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * List Lowongan Kerja versi portal — padanan JobOpeningResource admin.
 */
#[Layout('components.layout', ['title' => 'Lowongan Kerja'])]
class JobOpeningsPage extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:JobOpenings'), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
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

    protected function baseQuery()
    {
        $search = trim($this->search);

        return JobOpenings::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($search !== '', fn ($q) => $q->whereHas('jobPosition', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->withCount('applicants')
            ->with(['jobPosition.orgStructure', 'company']);
    }

    public function getJobOpeningsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10);
    }

    public function render()
    {
        return view('livewire.rekrutmen.job-openings-page');
    }
}
