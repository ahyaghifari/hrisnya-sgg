<?php

namespace App\Livewire\Counseling;

use App\Enum\CounselingStatus;
use App\Models\Counseling\Counseling;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Kelola Counseling" (HC) — list company-wide semua keluhan karyawan, gerbang permission Shield
 * `ViewAny:Counseling`. Padanan portal dari `/admin/counselings`.
 */
#[Layout('components.layout', ['title' => 'Kelola Counseling'])]
class CounselingManagePage extends Component
{
    use WithPagination;

    public ?string $companyId = null;
    public string $status = '';
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:Counseling'), 403);

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

    public function getStatusesProperty(): array
    {
        return CounselingStatus::cases();
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        return Counseling::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('issue', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->with('user');
    }

    public function getCounselingsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10);
    }

    public function render()
    {
        return view('livewire.counseling.counseling-manage-page');
    }
}
