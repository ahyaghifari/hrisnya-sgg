<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
use App\Services\Sp\SpCaseList;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "SP" — portal HC, padanan CoachingPage (planning/2026-09-23-sp-dan-merit-point.md §2.1). Beda
 * dari Coaching: SP tidak punya jalur "sekadar punya staff" - gerbangnya cuma
 * SISTEM_PEMBINAAN_KARYAWAN + ViewAny:EmployeeSp (HR/HC company-wide, SP itu keputusan HR bukan
 * urusan tiap atasan). Karyawan lihat SP-nya sendiri lewat SuratPeringatanPage yang terpisah.
 */
#[Layout('components.layout', ['title' => 'Surat Peringatan'])]
class SpPage extends Component
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

        if (! $this->canBrowseAllCompanies) {
            $this->companyId = $user->company_id;
        } elseif ($this->companyId === null) {
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

    protected function baseQuery()
    {
        $search = trim($this->search);

        return EmployeeSp::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with('user');
    }

    /** Satu daftar: tiap baris = Berita Acara + SP-nya (kalau sudah ada), dengan keterangan tahap. */
    public function getCasesProperty(): LengthAwarePaginator
    {
        $search = trim($this->search);

        $reports = SpReport::query()
            ->whereNotNull('status')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with(['user', 'sp'])
            ->get();

        $sps = $this->baseQuery()->with('report')->get();

        $cases = SpCaseList::merge($reports, $sps);
        $page = $this->getPage();

        return new LengthAwarePaginator($cases->forPage($page, 10)->values(), $cases->count(), 10, $page, [
            'path' => request()->url(),
            'query' => ['companyId' => $this->companyId, 'search' => $this->search],
        ]);
    }

    public function render()
    {
        return view('livewire.sp.sp-page');
    }
}
