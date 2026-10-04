<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Master\Companies;
use App\Models\User\UserTransition;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * List "Perubahan Status Karyawan" versi portal — padanan UserTransitionResource admin.
 * Pola nav kiri/kanan reuse .doc-type-nav/.doc-type-btn (sama seperti DocumentsPage) — kiri
 * pilih jenis transisi (Promosi/Demosi/Mutasi/PHK) dengan badge jumlah, kanan filter + tabel
 * jenis yang dipilih. Beda dari Dokumen: tidak ada status Aktif/Tidak Aktif (UserTransition
 * tidak soft-delete), diganti filter status approval (Menunggu/Disetujui/Ditolak).
 */
#[Layout('components.layout', ['title' => 'Perubahan Status Karyawan'])]
class UserTransitionsPage extends Component
{
    use WithPagination, GatesCompanyFilterBySystem;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $type = 'promotion';

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:UserTransition'), 403);

        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEPEGAWAIAN);

        if ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedCompanyId(): void
    {
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEPEGAWAIAN);

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
        return Auth::user()->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEPEGAWAIAN);
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('Create:UserTransition');
    }

    /** @return array<int, UserTransitionTypeEnum> */
    public function getTypesProperty(): array
    {
        return UserTransitionTypeEnum::assignable();
    }

    public function getStatusesProperty(): array
    {
        return UserTransitionStatusEnum::cases();
    }

    public function getTransitionCountsProperty()
    {
        return UserTransition::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->selectRaw('transition_type, count(*) as total')
            ->groupBy('transition_type')
            ->pluck('total', 'transition_type');
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        return UserTransition::query()
            ->where('transition_type', $this->type)
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($q3) => $q3->searchName($search))))
            ->with('user');
    }

    public function getTransitionsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10)->appends([
            'companyId' => $this->companyId,
            'type' => $this->type,
            'status' => $this->status,
            'search' => $this->search,
        ]);
    }

    public function render()
    {
        return view('livewire.kepegawaian.user-transitions-page');
    }
}
