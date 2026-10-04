<?php

namespace App\Livewire;

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
 * tetap sama persis: foto, nama+email, NIP, departemen, jabatan+role. SEBELUMNYA desktop pakai List.js (cari/sortir/pagination di
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
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->canAccessKaryawanMenu(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // MyStaff = HANYA staff langsung + tidak langsung (rekursif via OrgHierarchyService),
    // tanpa pemilih perusahaan. Beda dari halaman Kepegawaian > Karyawan yang company-wide
    // (instruksi user 2026-09-03) — siapa pun (termasuk yang punya LIHAT_PERUSAHAAN_LAIN)
    // di halaman ini cuma lihat struktur di bawahnya sendiri.
    protected function baseQuery()
    {
        $query = app(OrgHierarchyService::class)->subordinates(Auth::user());

        $query->with(['profile.jobPosition.orgStructure.parent.parent', 'profile.jobPosition.orgStructure.parent.parent', 'company', 'roles']);

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->searchNameOrNip($search)
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('profile.jobPosition', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    // Dipakai grid kartu di my-employees.blade.php.
    public function getEmployeesProperty()
    {
        return $this->baseQuery()
            ->orderByName()
            ->paginate(20)
            ->appends(['search' => $this->search]);
    }

    public function render()
    {
        return view('livewire.my-employees');
    }
}
