<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\EmployeeTypeCategory;
use App\Models\ContractSubmission;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sisi HR untuk modul Perpanjangan Kontrak. 2 tab:
 *  - aktif  : karyawan kategori Kontrak dengan kontrak berjalan — 1 baris/orang, periode +
 *    sisa hari + status pengajuan perpanjangan terakhir + keputusan + aksi.
 *  - riwayat: pengajuan perpanjangan yang sudah selesai/ditolak.
 */
#[Layout('components.layout', ['title' => 'Perpanjangan Kontrak'])]
class KontrakKelolaPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** aktif | riwayat */
    #[Url]
    public string $tab = 'aktif';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', ContractSubmission::class), 403);

        if (! Auth::user()->canFilterByCompany() || $this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
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

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::orderBy('code')->pluck('name', 'id');
    }

    public function getKontrakKaryawanProperty()
    {
        return User::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->where(fn ($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%"));
            })
            ->whereHas('profile.employeeType', fn ($q) => $q->where('category', EmployeeTypeCategory::KONTRAK->value))
            ->whereHas('contracts')
            ->with([
                'company',
                'currentContract',
                'contractSubmissions' => fn ($q) => $q->with('decidedBy')->latest(),
            ])
            ->orderBy('name')
            ->paginate(15)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search, 'tab' => $this->tab]);
    }

    public function getRiwayatProperty()
    {
        return ContractSubmission::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->whereIn('status', ['selesai', 'ditolak'])
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('user', function ($q2) {
                $s = trim($this->search);
                $q2->where('name', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%");
            }))
            ->with(['user', 'decidedBy', 'newContract'])
            ->latest('decided_at')
            ->paginate(15)
            ->appends(['companyId' => $this->companyId, 'search' => $this->search, 'tab' => $this->tab]);
    }

    public function render()
    {
        return view('livewire.kepegawaian.kontrak-kelola-page');
    }
}
