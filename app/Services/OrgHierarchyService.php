<?php

namespace App\Services;

use App\Models\Master\JobPositions;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrgHierarchyService
{
    /**
     * ID semua Job Position di bawah $jobPosition, rekursif ke semua level.
     */
    public function subordinateJobPositionIds(JobPositions $jobPosition): array
    {
        $ids = [];
        $queue = [$jobPosition->id];

        while ($queue) {
            $childIds = JobPositions::where('parent_id', array_pop($queue))->pluck('id')->all();

            foreach ($childIds as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * Query user yang menempati Job Position di bawah $user, rekursif ke semua level.
     */
    public function subordinates(User $user): Builder
    {
        $jobPosition = $user->activeJobPosition?->jobPosition;

        if (! $jobPosition) {
            return User::query()->whereRaw('1 = 0');
        }

        $subordinateJobPositionIds = $this->subordinateJobPositionIds($jobPosition);

        return User::query()
            ->whereHas('activeJobPosition', fn ($q) => $q->whereIn('job_position_id', $subordinateJobPositionIds))
            ->with(['profile.jobPosition', 'profile.orgStructure']);
    }
}
