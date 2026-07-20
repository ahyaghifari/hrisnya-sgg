<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Daftar SEMUA karyawan company (bukan cuma bawahan seperti MyEmployees/MyStaff) — padanan
 * portal dari admin EmployeeResource. Pola desktop-tabel/mobile-card sama persis dengan
 * MyEmployees, lihat DESIGN.md § Responsive requirement.
 */
#[Layout('components.layout', ['title' => 'Karyawan'])]
class KaryawanPage extends Component
{
    public ?string $companyId = null;
    public ?string $departmentId = null;
    public string $search = '';
    public string $status = 'aktif';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);

        if (! PermissionHelper::hasGlobalRole(Auth::user())) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        $this->departmentId = null;
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
    }

    protected function baseQuery()
    {
        // Default cuma karyawan aktif (query normal, exclude soft-deleted otomatis) — "Tidak
        // Aktif" khusus nampilin yang sudah dinonaktifkan/dihapus, buat dipulihkan kalau perlu.
        $query = $this->status === 'tidak_aktif' ? User::onlyTrashed() : User::query();

        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        } elseif (! PermissionHelper::hasGlobalRole(Auth::user())) {
            $query->where('company_id', Auth::user()->company_id);
        }

        if ($this->departmentId) {
            // org_structure_id karyawan bisa langsung department (level 1), divisi di
            // bawahnya (level 2, parent_id = department), atau sub divisi (level 3,
            // parent.parent_id = department) — cocokkan ketiganya.
            $deptId = $this->departmentId;
            $query->whereHas('profile.orgStructure', function ($oq) use ($deptId) {
                $oq->where('id', $deptId)
                    ->orWhere('parent_id', $deptId)
                    ->orWhereHas('parent', fn ($pq) => $pq->where('parent_id', $deptId));
            });
        }

        // 'department' itu accessor (getDepartmentAttribute), bukan relation — eager-load
        // orgStructure.parent.parent (sama pola MyEmployees) supaya accessor-nya tidak N+1.
        $query->with(['profile.jobPosition', 'profile.orgStructure.parent.parent', 'company', 'roles']);

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
        return $this->baseQuery()->orderBy('name')->paginate(10);
    }

    public function restore(string $id): void
    {
        $employee = User::onlyTrashed()->findOrFail($id);

        abort_unless(
            PermissionHelper::hasGlobalRole(Auth::user()) || $employee->company_id === Auth::user()->company_id,
            403
        );

        $employee->restore();
    }

    public function render()
    {
        return view('livewire.kepegawaian.karyawan-page', [
            'canBrowseAllCompanies' => PermissionHelper::hasGlobalRole(Auth::user()),
        ]);
    }
}
