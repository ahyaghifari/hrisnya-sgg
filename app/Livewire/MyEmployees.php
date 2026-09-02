<?php

namespace App\Livewire;

use App\Models\Master\Companies;
use App\Models\User;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tabel desktop dulu reuse Filament (EmployeesTable + InteractsWithTable) — dicabut total,
 * ganti custom + Livewire (server-side, sama sumbernya dengan card mobile di bawah), kolomnya
 * tetap sama persis: foto, nama+email, NIP, departemen, jabatan+role, perusahaan (cuma buat
 * yang punya akses global). SEBELUMNYA desktop pakai List.js (cari/sortir/pagination di
 * browser), tapi List.js mengunci baris tbody-nya sendiri sehingga tidak ikut ke-morph Livewire
 * tiap company/search ganti (baris lama nyangkut terus), jadi dicabut — desktop sekarang reuse
 * `$this->employees` (paginated) yang sama dengan mobile, bukan query terpisah tanpa pagination.
 */
#[Layout('components.layout', ['title' => 'MyStaff'])]
class MyEmployees extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->canAccessKaryawanMenu(), 403);

        // Super Admin defaultnya diarahkan ke company sendiri dulu (bukan list kosong sampai
        // pilih manual, lihat baseQuery() - companyId kosong = whereRaw('1=0')), tapi tetap
        // bisa pindah company lain lewat dropdown.
        if ($user->canFilterByCompany() && $this->companyId === null) {
            $this->companyId = $user->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
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

    // Sumber data yang sama dipakai desktop (tabel) dan mobile (card manual).
    protected function baseQuery()
    {
        if (Auth::user()->canFilterByCompany()) {
            if (! $this->companyId) {
                return User::query()->whereRaw('1 = 0');
            }

            $query = User::query()->where('company_id', $this->companyId);
        } else {
            $query = app(OrgHierarchyService::class)->subordinates(Auth::user());
        }

        $query->with(['profile.jobPosition.orgStructure.parent.parent', 'profile.orgStructure.parent.parent', 'company', 'roles']);

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhereHas('profile.jobPosition', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    // Dipakai mobile (card manual) DAN desktop (tabel), lihat my-employees.blade.php.
    public function getEmployeesProperty()
    {
        return $this->baseQuery()
            ->orderBy('name')
            ->paginate(10)
            ->appends([
                'companyId' => $this->companyId,
                'search' => $this->search,
            ]);
    }

    public function render()
    {
        return view('livewire.my-employees', [
            'canBrowseAllCompanies' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
