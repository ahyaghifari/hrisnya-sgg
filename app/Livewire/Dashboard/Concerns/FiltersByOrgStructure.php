<?php

namespace App\Livewire\Dashboard\Concerns;

use App\Enum\OrgStructurLevel;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Illuminate\Support\Collection;

/**
 * Filter cascading Departemen -> Divisi, dipakai bareng oleh widget dashboard yang butuh
 * drill-down org structure (ManpowerGapWidget, AttendanceTrendWidget - instruksi user
 * 2026-09-18). Filter cuma sampai Divisi - begitu Divisi dipilih, jabatan-jabatan di
 * dalamnya kelihatan sebagai HASIL breakdown widget (bukan level filter tambahan). Class
 * yang makai WAJIB punya public string $companyId (widget dashboard semuanya sudah punya
 * ini, lihat komentar $companyId di app/Livewire/Dashboard.php).
 *
 * JobPositions.org_structure_id bisa menunjuk ke level mana pun (Departemen/Divisi/Sub
 * Divisi) - departmentOf()/divisionOf() jalan ke atas rantai parent buat resolve level yang
 * relevan, bukan asumsikan org_structure langsung levelnya pas.
 */
trait FiltersByOrgStructure
{
    public ?string $selectedDepartmentId = null;
    public ?string $selectedDivisionId = null;

    public function updatedSelectedDepartmentId(): void
    {
        $this->selectedDivisionId = null;
    }

    public function getDepartmentOptionsProperty()
    {
        return OrgStructure::query()
            ->where('company_id', $this->companyId)
            ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function getDivisionOptionsProperty()
    {
        if (! $this->selectedDepartmentId) {
            return collect();
        }

        return OrgStructure::query()
            ->where('company_id', $this->companyId)
            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
            ->where('parent_id', $this->selectedDepartmentId)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    protected function departmentOf(?OrgStructure $org): ?OrgStructure
    {
        while ($org) {
            if ($org->level === OrgStructurLevel::LEVEL_DEPARTMENT->value) {
                return $org;
            }
            $org = $org->parent;
        }

        return null;
    }

    protected function divisionOf(?OrgStructure $org): ?OrgStructure
    {
        while ($org) {
            if ($org->level === OrgStructurLevel::LEVEL_DIVISION->value) {
                return $org;
            }
            if ($org->level === OrgStructurLevel::LEVEL_DEPARTMENT->value) {
                return null;
            }
            $org = $org->parent;
        }

        return null;
    }

    /** Kumpulan job_position_id sesuai filter aktif (Divisi kalau dipilih, else Departemen) -
     * null kalau belum ada filter sama sekali (artinya seluruh company, tidak perlu dibatasi). */
    protected function scopedJobPositionIds(): ?Collection
    {
        if (! $this->selectedDepartmentId) {
            return null;
        }

        $positions = JobPositions::query()
            ->where('company_id', $this->companyId)
            ->with('orgStructure.parent.parent')
            ->get();

        if ($this->selectedDivisionId) {
            return $positions
                ->filter(fn (JobPositions $jp) => $this->divisionOf($jp->orgStructure)?->id === $this->selectedDivisionId)
                ->pluck('id');
        }

        return $positions
            ->filter(fn (JobPositions $jp) => $this->departmentOf($jp->orgStructure)?->id === $this->selectedDepartmentId)
            ->pluck('id');
    }
}
