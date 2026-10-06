<?php

namespace App\Services;

use App\Enum\OrgStructurLevel;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\SikartapDirectSupervisorPosition;
use App\Models\SikartapReviewer;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Support\Collection;

/**
 * Resolve penilai buat milestone perjalanan status karyawan (`sikartap_reviewers`). Reviewer
 * ditunjuk per JABATAN — dua mode, per (context, company, jabatan target):
 *
 * 1. **Atasan langsung** (`sikartap_direct_supervisor_positions`, 2026-09-17 instruksi user —
 *    kasus RS: perawat dinilai kepala ruangan masing-masing, bukan 1 jabatan se-departemen).
 *    Jabatan target ditandai di sini → resolve ke pemegang aktif JobPositions.parent_id,
 *    naik terus ke atasan dari atasan kalau kosong.
 * 2. **By Departemen** (skema lama, default kalau jabatan TIDAK ditandai atasan-langsung):
 *    naik dari jabatan karyawan sampai node level Departemen (LEVEL_DEPARTMENT), cari baris
 *    `sikartap_reviewers` (context, company_id, org_structure_id) di departemen itu, resolve
 *    ke pemegang aktif jabatan yang ditunjuk — kosong → naik ke departemen parent.
 *
 * Mentok atas tanpa hasil (mode manapun) → collection kosong; pemanggil yang menolak aksi +
 * minta HR isi.
 */
class SikartapReviewerResolver
{
    /** @return Collection<int, User> */
    public function reviewersFor(string $context, User $target): Collection
    {
        $jobPosition = $target->activeJobPosition?->jobPosition;

        if ($jobPosition && $this->usesDirectSupervisor($context, $target->company_id, $jobPosition->id)) {
            return $this->directSupervisorHolders($jobPosition);
        }

        $node = $this->departmentOf($target);

        while ($node) {
            $holders = $this->holdersForJobPositionIds(
                SikartapReviewer::forContext($context)
                    ->where('company_id', $target->company_id)
                    ->where('org_structure_id', $node->id)
                    ->pluck('job_position_id')
            );

            if ($holders->isNotEmpty()) {
                return $holders;
            }

            $node = $node->parent;
        }

        return collect();
    }

    private function usesDirectSupervisor(string $context, ?string $companyId, string $jobPositionId): bool
    {
        return SikartapDirectSupervisorPosition::forContext($context)
            ->where('company_id', $companyId)
            ->where('job_position_id', $jobPositionId)
            ->exists();
    }

    /** @return Collection<int, User> */
    private function directSupervisorHolders(JobPositions $jobPosition): Collection
    {
        $node = $jobPosition->parent;

        while ($node) {
            $holders = $this->holdersForJobPositionIds(collect([$node->id]));

            if ($holders->isNotEmpty()) {
                return $holders;
            }

            $node = $node->parent;
        }

        return collect();
    }

    /** Atasan langsung = pemegang aktif jabatan parent terdekat (kosong -> naik lagi). */
    public function directSupervisorOf(User $user): ?User
    {
        $jobPosition = $user->activeJobPosition?->jobPosition;

        return $jobPosition ? $this->directSupervisorHolders($jobPosition)->first() : null;
    }

    public function canReview(string $context, User $target, User $actor): bool
    {
        return $this->reviewersFor($context, $target)->contains('id', $actor->id);
    }

    /** @param Collection<int, string> $jobPositionIds
     *  @return Collection<int, User> */
    private function holdersForJobPositionIds(Collection $jobPositionIds): Collection
    {
        if ($jobPositionIds->isEmpty()) {
            return collect();
        }

        return UserJobPositions::query()
            ->whereIn('job_position_id', $jobPositionIds)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }

    private function departmentOf(User $user): ?OrgStructure
    {
        $node = $user->activeJobPosition?->jobPosition?->orgStructure;

        while ($node && $node->level !== OrgStructurLevel::LEVEL_DEPARTMENT->value) {
            $node = $node->parent;
        }

        return $node;
    }
}
