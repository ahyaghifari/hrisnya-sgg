<?php

namespace App\Livewire\Coaching;

use App\Enum\CoachingStatus;
use App\Models\Coaching\Coaching;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Coaching" (HC) — list company-wide (company-scoped, bukan subordinates/departemen — Coaching
 * & Counseling dikelola 1 role khusus per company, gerbang permission Shield `*:Coaching` saja).
 */
#[Layout('components.layout', ['title' => 'Coaching'])]
class CoachingPage extends Component
{
    use WithPagination;

    public ?string $companyId = null;
    public string $status = '';
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:Coaching'), 403);

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

    public function setStatus(string $status): void
    {
        $this->status = $status;
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
        return Auth::user()->can('Create:Coaching');
    }

    public function getStatusesProperty(): array
    {
        return CoachingStatus::cases();
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        return Coaching::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with(['user', 'coachBy']);
    }

    public function getCoachingsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10);
    }

    public function render()
    {
        return view('livewire.coaching.coaching-page');
    }
}
