<?php

namespace App\Livewire\Kepegawaian;

use App\Models\Master\Companies;
use App\Models\User\UserContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Padanan portal dari admin UserContractResource — read-only (lihat/download surat), sama pola
 * DocumentsPage (kelola tetap di admin Filament). Bagian dari grup nav baru "Status Karyawan"
 * (2026-08-22, instruksi user) - "Tetap" belum ada halamannya (placeholder "Segera" di nav).
 */
#[Layout('components.layout', ['title' => 'Kontrak Karyawan'])]
class KontrakPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** 'semua' | 'aktif' | 'berakhir' - berdasar contract_end_date terhadap hari ini. */
    #[Url]
    public string $status = 'semua';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', UserContract::class), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
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

    public function getContractsProperty()
    {
        $today = Carbon::today()->toDateString();

        return UserContract::query()
            ->with(['user.company', 'employeeType'])
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status === 'aktif', fn ($q) => $q->where('contract_end_date', '>=', $today))
            ->when($this->status === 'berakhir', fn ($q) => $q->where('contract_end_date', '<', $today))
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);

                $q->whereHas('user', fn ($q2) => $q2
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"));
            })
            ->orderByDesc('contract_start_date')
            ->paginate(10)
            ->appends([
                'companyId' => $this->companyId,
                'search' => $this->search,
                'status' => $this->status,
            ]);
    }

    public function render()
    {
        return view('livewire.kepegawaian.kontrak-page', [
            'canBrowseAllCompanies' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
