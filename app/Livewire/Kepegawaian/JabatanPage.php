<?php

namespace App\Livewire\Kepegawaian;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar Jabatan portal - padanan JobPositionResource admin (tabel + slide-over History),
 * tanpa Wizard/lock detail form (itu di CreateJabatanPage/EditJabatanPage). Kolom sama:
 * Nama Jabatan, Unit, Kuota, Jumlah Karyawan, Status.
 */
#[Layout('components.layout', ['title' => 'Jabatan'])]
class JabatanPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public ?string $historyRecordId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', JobPositions::class), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
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

    public function openHistory(string $id): void
    {
        $this->historyRecordId = $id;
    }

    public function closeHistory(): void
    {
        $this->historyRecordId = null;
    }

    public function getHistoryRecordProperty(): ?JobPositions
    {
        if (! $this->historyRecordId) {
            return null;
        }

        return JobPositions::with('approvalHistories')->find($this->historyRecordId);
    }

    protected function baseQuery(): Builder
    {
        $query = JobPositions::query()->with(['orgStructure', 'pendingQuotaRequest']);

        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        } elseif (! Auth::user()->canFilterByCompany()) {
            $query->where('company_id', Auth::user()->company_id);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query;
    }

    public function getJabatanProperty()
    {
        return $this->baseQuery()
            ->withCount(['userAssignments'])
            ->orderBy('name')
            ->paginate(15)
            ->appends([
                'companyId' => $this->companyId,
                'search' => $this->search,
            ]);
    }

    public function render()
    {
        return view('livewire.kepegawaian.jabatan-page');
    }
}
