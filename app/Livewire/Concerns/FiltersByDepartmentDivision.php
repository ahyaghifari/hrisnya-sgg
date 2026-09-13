<?php

namespace App\Livewire\Concerns;

use App\Enum\OrgStructurLevel;
use App\Models\Master\OrgStructure;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Dipakai halaman portal yang punya filter company ($companyId) dan mau tambah filter
 * Departemen/Divisi - satu tempat buat logic pencocokan struktur organisasi (dept level 1,
 * divisi level 2, sub divisi level 3) yang sebelumnya cuma ada di KaryawanPage, supaya rekap
 * presensi/izin/cuti/lembur bisa reuse tanpa duplikasi query matching.
 */
trait FiltersByDepartmentDivision
{
    #[Url]
    public ?string $departmentId = null;

    #[Url]
    public ?string $divisionId = null;

    public function updatedDepartmentId(): void
    {
        $this->divisionId = null;

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function updatedDivisionId(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
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

    public function getDivisionOptionsProperty()
    {
        if (! $this->departmentId) {
            return collect();
        }

        return OrgStructure::where('company_id', $this->companyId)
            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
            ->where('parent_id', $this->departmentId)
            ->pluck('name', 'id');
    }

    /** $userQuery = query builder yang model dasarnya User (langsung User::query() ATAU builder
     * di dalam whereHas('user', ...)/whereHas('attendanceRequest.user', ...)) - relasi
     * profile.jobPosition.orgStructure & profile.orgStructure tetap resolve sama persis. */
    protected function applyDepartmentDivisionFilter(Builder $userQuery): void
    {
        if ($this->departmentId) {
            $deptId = $this->departmentId;
            $matchDept = function ($oq) use ($deptId) {
                $oq->where('id', $deptId)
                    ->orWhere('parent_id', $deptId)
                    ->orWhereHas('parent', fn ($pq) => $pq->where('parent_id', $deptId));
            };
            $userQuery->where(fn ($q) => $q
                ->whereHas('profile.jobPosition.orgStructure', $matchDept)
                ->orWhereHas('profile.orgStructure', $matchDept));
        }

        if ($this->divisionId) {
            $divId = $this->divisionId;
            $matchDiv = function ($oq) use ($divId) {
                $oq->where('id', $divId)->orWhere('parent_id', $divId);
            };
            $userQuery->where(fn ($q) => $q
                ->whereHas('profile.jobPosition.orgStructure', $matchDiv)
                ->orWhereHas('profile.orgStructure', $matchDiv));
        }
    }
}
