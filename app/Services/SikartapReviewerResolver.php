<?php

namespace App\Services;

use App\Enum\OrgStructurLevel;
use App\Models\Master\OrgStructure;
use App\Models\SikartapReviewer;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Support\Collection;

/**
 * Resolve penilai buat milestone perjalanan status karyawan (`sikartap_reviewers`). Reviewer
 * ditunjuk per JABATAN di level Departemen — di-resolve ke pemegang aktif jabatan itu.
 *
 * Aturan (2026-09-06, instruksi user):
 * - Naik dari jabatan karyawan sampai node level Departemen (LEVEL_DEPARTMENT).
 * - Cari baris `sikartap_reviewers` (context, company_id, org_structure_id) di departemen itu.
 * - Jabatan yang ditunjuk kosong pemegang aktif → FALLBACK naik ke departemen parent.
 * - Mentok atas tanpa hasil → collection kosong; pemanggil yang menolak aksi + minta HR isi
 *   ("Jabatan penilai untuk departemen ini kosong").
 */
class SikartapReviewerResolver
{
    /** @return Collection<int, User> */
    public function reviewersFor(string $context, User $target): Collection
    {
        $node = $this->departmentOf($target);

        while ($node) {
            $holders = $this->holdersForNode($context, $target->company_id, $node->id);

            if ($holders->isNotEmpty()) {
                return $holders;
            }

            $node = $node->parent;
        }

        return collect();
    }

    public function canReview(string $context, User $target, User $actor): bool
    {
        return $this->reviewersFor($context, $target)->contains('id', $actor->id);
    }

    /** @return Collection<int, User> */
    private function holdersForNode(string $context, ?string $companyId, string $orgStructureId): Collection
    {
        $jobPositionIds = SikartapReviewer::forContext($context)
            ->where('company_id', $companyId)
            ->where('org_structure_id', $orgStructureId)
            ->pluck('job_position_id');

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
