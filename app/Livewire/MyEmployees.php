<?php

namespace App\Livewire;

use App\Models\Master\Companies;
use App\Models\User;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tabel desktop dulu reuse Filament (EmployeesTable + InteractsWithTable) — dicabut total,
 * ganti custom + List.js (cari/sortir/pagination instan di browser, lihat resources/js/app.js),
 * kolomnya tetap sama persis: foto, nama+email, NIP, departemen, jabatan+role, perusahaan
 * (cuma buat yang punya akses global). Card mobile (di bawah) tidak berubah.
 */
#[Layout('components.layout', ['title' => 'MyStaff'])]
class MyEmployees extends Component
{
    public ?string $companyId = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->canAccessKaryawanMenu(), 403);
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    // Sumber data yang sama dipakai desktop (Filament Table) dan mobile (card manual).
    protected function baseQuery()
    {
        if (Auth::user()->canBrowseAllCompanies()) {
            if (! $this->companyId) {
                return User::query()->whereRaw('1 = 0');
            }

            return User::query()
                ->where('company_id', $this->companyId)
                ->with(['profile.jobPosition', 'profile.orgStructure.parent.parent', 'company', 'roles']);
        }

        return app(OrgHierarchyService::class)->subordinates(Auth::user())
            ->with(['profile.jobPosition', 'profile.orgStructure.parent.parent', 'company', 'roles']);
    }

    // Dipakai mobile (card manual, lihat my-employees.blade.php) — tetap dipaginasi server,
    // tidak disentuh (di luar scope perubahan tabel desktop).
    public function getEmployeesProperty()
    {
        return $this->baseQuery()
            ->orderBy('name')
            ->paginate(10);
    }

    // Dipakai desktop (tabel custom + List.js) — dimuat sekali tanpa pagination server, List.js
    // urus cari/sortir/pagination-nya di browser.
    public function getStaffTableRowsProperty()
    {
        return $this->baseQuery()->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.my-employees', [
            'canBrowseAllCompanies' => Auth::user()->canBrowseAllCompanies(),
        ]);
    }
}
