<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\ManpowerRequestType;
use App\Models\Job\ManpowerRequests;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * List Permintaan Tenaga Kerja versi portal — padanan ManpowerRequestResource admin. Filter
 * Tipe (select biasa, tanpa count, di kiri kolom Perusahaan/Cari) + badge tipe di tiap baris
 * (instruksi user 2026-08-07).
 *
 * Sengaja EXCLUDE tipe ADDITIONAL_QUOTA (2026-08-09) — halaman ini fokus ke permintaan yang
 * beneran butuh rekrut (Tambah Karyawan/Penggantian, keduanya buka JobOpenings). Pengajuan
 * kuota sekarang entry-point-nya di halaman Jabatan (lihat EditJabatanPage::getQuotaRequestsProperty()
 * + partial jabatan-quota-section), datanya tetap sama-sama di tabel ManpowerRequests, cuma
 * tempat munculnya beda. Approval Engine & backend TIDAK berubah.
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
    public string $type = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:ManpowerRequests'), 403);

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

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getTypeOptionsProperty(): array
    {
        return collect(ManpowerRequestType::cases())
            ->reject(fn (ManpowerRequestType $type) => $type === ManpowerRequestType::ADDITIONAL_QUOTA)
            ->mapWithKeys(fn (ManpowerRequestType $type) => [$type->value => $type->getLabel()])
            ->all();
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('Create:ManpowerRequests');
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        return ManpowerRequests::query()
            ->where('type', '!=', ManpowerRequestType::ADDITIONAL_QUOTA->value)
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($search !== '', fn ($q) => $q->whereHas('jobPosition', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with(['jobPosition.orgStructure', 'company']);
    }

    public function getRequestsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10)->appends([
            'companyId' => $this->companyId,
            'type' => $this->type,
            'search' => $this->search,
        ]);
    }

    public function render()
    {
        return view('livewire.rekrutmen.manpower-requests-page');
    }
}
