<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Exports\Kepegawaian\KaryawanDataExport;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Daftar SEMUA karyawan company (bukan cuma bawahan seperti MyEmployees/MyStaff) — padanan
 * portal dari admin EmployeeResource. Pola desktop-tabel/mobile-card sama persis dengan
 * MyEmployees, lihat DESIGN.md § Responsive requirement.
 */
#[Layout('components.layout', ['title' => 'Karyawan'])]
class KaryawanPage extends Component
{
    use WithPagination, \App\Livewire\Concerns\GatesCompanyFilterBySystem;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL — bukan wire:click
    // di komponen yang sama. Tanpa #[Url], filter yang bukan bagian dari URL query string ikut
    // hilang begitu pindah halaman paginasi. #[Url] menyimpan nilainya di query string supaya
    // filter tetap kebawa lintas navigasi paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public ?string $departmentId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'aktif';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', User::class), 403);

        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEPEGAWAIAN);

        if ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEPEGAWAIAN);

        $this->departmentId = null;
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
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

    public function getDepartmentOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return OrgStructure::where('company_id', $this->companyId)
            ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->pluck('name', 'id');
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    protected function baseQuery()
    {
        // 3 tab beda konsep, jangan dicampur:
        // - aktif: is_active=true, belum dihapus (default)
        // - tidak_aktif: is_active=false TAPI masih ada di sistem (di-nonaktifkan lewat toggle
        //   Edit Karyawan) - beda dari arsip, karyawan ini masih bisa diaktifkan lagi kapan saja
        // - arsip: soft-deleted (dihapus) - dulu ini yang salah dipakai buat tab "Tidak Aktif"
        $query = match ($this->status) {
            'tidak_aktif' => User::query()->where('is_active', false),
            'arsip' => User::onlyTrashed(),
            default => User::query()->where('is_active', true),
        };

        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        } elseif (! Auth::user()->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEPEGAWAIAN)) {
            $query->where('company_id', Auth::user()->company_id);
        }

        if ($this->departmentId) {
            // org_structure_id karyawan bisa langsung department (level 1), divisi di
            // bawahnya (level 2, parent_id = department), atau sub divisi (level 3,
            // parent.parent_id = department) — cocokkan ketiganya.
            $deptId = $this->departmentId;
            $matchDept = function ($oq) use ($deptId) {
                $oq->where('id', $deptId)
                    ->orWhere('parent_id', $deptId)
                    ->orWhereHas('parent', fn ($pq) => $pq->where('parent_id', $deptId));
            };
            // Cocokkan lewat struktur efektif: jabatan.orgStructure ATAU profile.orgStructure
            // (sama seperti getDepartmentAttribute yang mengutamakan jabatan).
            $query->where(fn ($q) => $q
                ->whereHas('profile.jobPosition.orgStructure', $matchDept)
                ->orWhereHas('profile.orgStructure', $matchDept));
        }

        // 'department' itu accessor (getDepartmentAttribute) yang baca struktur efektif =
        // jabatan.orgStructure ?? profile.orgStructure — eager-load dua-duanya biar tidak N+1.
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

    public function getEmployeesProperty()
    {
        return $this->baseQuery()->orderBy('name')->paginate(10)->appends([
            'companyId' => $this->companyId,
            'departmentId' => $this->departmentId,
            'search' => $this->search,
            'status' => $this->status,
        ]);
    }

    /** Pulihkan karyawan yang sudah DIHAPUS (soft-deleted, tab Arsip) - beda dari activate(). */
    public function restore(string $id): void
    {
        $employee = User::onlyTrashed()->findOrFail($id);

        abort_unless(
            PermissionHelper::hasGlobalRole(Auth::user()) || $employee->company_id === Auth::user()->company_id,
            403
        );
        abort_unless(Auth::user()->can('restore', User::class), 403);

        $employee->restore();
    }

    /** Aktifkan lagi karyawan yang di-nonaktifkan (is_active=false, tab Tidak Aktif) - beda dari
     * restore(), karyawan ini tidak pernah dihapus sama sekali. */
    public function activate(string $id): void
    {
        $employee = User::where('is_active', false)->findOrFail($id);

        abort_unless(
            PermissionHelper::hasGlobalRole(Auth::user()) || $employee->company_id === Auth::user()->company_id,
            403
        );
        abort_unless(Auth::user()->can('update', User::class), 403);

        $employee->update(['is_active' => true]);
    }

    /** Hapus PERMANEN karyawan yang sudah di-arsip (soft-deleted) - beda dari restore()/
     * activate(), tidak bisa dipulihkan lagi setelah ini. Digerbangi permission terpisah
     * (ForceDelete:User) dari Delete:User biasa (soft-delete) - role yang boleh hapus biasa
     * belum tentu boleh hapus permanen. */
    public function forceDeleteEmployee(string $id): void
    {
        $employee = User::onlyTrashed()->findOrFail($id);

        abort_unless(
            PermissionHelper::hasGlobalRole(Auth::user()) || $employee->company_id === Auth::user()->company_id,
            403
        );
        abort_unless(Auth::user()->can('forceDelete', User::class), 403);

        try {
            $employee->forceDelete();
        } catch (\Illuminate\Database\QueryException $e) {
            \Filament\Notifications\Notification::make()
                ->title('Tidak bisa dihapus permanen')
                ->body('Karyawan ini masih punya data terkait (presensi, payroll, dll) yang mengunci penghapusan.')
                ->danger()
                ->send();

            return;
        }

        \Filament\Notifications\Notification::make()
            ->title('Karyawan berhasil dihapus permanen')
            ->success()
            ->send();
    }

    /** Export Excel data karyawan (akun+pribadi+pekerjaan) sesuai filter/pencarian aktif saat
     * tombol diklik - reuse baseQuery() supaya hasil export selalu konsisten dengan yang
     * ditampilkan di tabel/list, bukan query terpisah yang bisa menyimpang. */
    public function export()
    {
        abort_unless(Auth::user()->can(\App\Enum\CustomPermissionEnum::EXPORT_DATA_KARYAWAN->value), 403);

        $employees = $this->baseQuery()
            ->with(['profile.religion', 'profile.pangkat', 'profile.kelas', 'profile.employeeType', 'profile.jobPosition.orgStructure.parent.parent', 'profile.orgStructure.parent.parent'])
            ->get();

        return Excel::download(
            new KaryawanDataExport($employees, includeCompanyColumn: $this->companyId === null),
            'data-karyawan-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function render()
    {
        return view('livewire.kepegawaian.karyawan-page', [
            'canBrowseAllCompanies' => Auth::user()->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEPEGAWAIAN),
        ]);
    }
}
