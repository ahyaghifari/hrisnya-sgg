<?php

namespace App\Livewire\Counseling;

use App\Enum\CounselingStatus;
use App\Enum\CustomPermissionEnum;
use App\Models\Counseling\Counseling;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Counseling" (sisi konselor/HR) — company-wide (2026-08-17, redesign dari gerbang lama yang
 * cuma nampilin punya konselor sendiri), digerbangi permission SISTEM_PEMBINAAN_KARYAWAN.
 * Record SELALU tampil di list (siapa pun konselornya), tapi isi issue/response/solution
 * di-redact "🔒 Rahasia" kalau viewer bukan user_id/counselor_id record itu DAN record-nya
 * is_secret=true — lihat Counseling::isContentVisibleTo(). Search cuma di nama karyawan, bukan
 * isi keluhan (issue terenkripsi, tidak bisa di-LIKE query DB).
 */
#[Layout('components.layout', ['title' => 'Counseling'])]
class CounselingManagePage extends Component
{
    use WithPagination;

    public ?string $companyId = null;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    // Gerbang di sini saja tidak cukup - Livewire property bisa di-$set langsung dari request
    // yang di-craft (lewat hook updated*, bukan lewat method public), jadi companyId perlu
    // divalidasi ulang di sini juga.
    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->resetPage();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
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

    public function getStatusesProperty(): array
    {
        return CounselingStatus::cases();
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        return Counseling::query()
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with(['user', 'counselor']);
    }

    public function getCounselingsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10)->appends([
            'status' => $this->status,
            'search' => $this->search,
        ]);
    }

    public function render()
    {
        return view('livewire.counseling.counseling-manage-page');
    }
}
