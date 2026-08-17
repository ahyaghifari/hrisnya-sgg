<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat/daftar Alur Persetujuan - versi portal dari admin Filament ApprovalFlowResource
 * (app/Filament/Resources/ApprovalFlows/*), ditaruh di modul Kepegawaian (instruksi user
 * 2026-08-16). Super Admin (holding, canFilterByCompany()) bisa filter/lihat semua company,
 * admin company-scoped cuma lihat company sendiri - pola sama ApprovalFlowsTable.php.
 */
#[Layout('components.layout', ['title' => 'Alur Persetujuan'])]
class AlurPersetujuanListPage extends Component
{
    use WithPagination;

    #[Url]
    public string $companyFilter = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_ALUR_PERSETUJUAN->value), 403);
    }

    public function getCanFilterByCompanyProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function setCompanyFilter(string $companyId): void
    {
        $this->companyFilter = $companyId;
        $this->resetPage();
    }

    public function getCompaniesProperty()
    {
        return $this->canFilterByCompany ? Companies::orderBy('name')->get(['id', 'name']) : collect();
    }

    public function getFlowsProperty()
    {
        return MasterApprovalFlows::query()
            ->with(['company', 'steps'])
            ->when(! $this->canFilterByCompany, fn ($q) => $q->where('company_id', Auth::user()->company_id))
            ->when($this->canFilterByCompany && $this->companyFilter, fn ($q) => $q->where('company_id', $this->companyFilter))
            ->orderBy('name')
            ->paginate(15)
            ->appends(['companyFilter' => $this->companyFilter]);
    }

    public function moduleLabel(string $module): string
    {
        return ApprovalModuleEnum::tryFrom($module)?->label() ?? $module;
    }

    public function render()
    {
        return view('livewire.kepegawaian.alur-persetujuan-list-page');
    }
}
