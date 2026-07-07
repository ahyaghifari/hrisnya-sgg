<?php

namespace App\Repositories\Approval;

use App\Models\ApprovalManagement\ApprovalActivities;

class ApprovalActivityRepository
{
    public function create(array $data): ApprovalActivities
    {
        return ApprovalActivities::create($data);
    }

    public function findBySnapshot(string $snapshotId): ?ApprovalActivities
    {
        return ApprovalActivities::where('snapshot_id', $snapshotId)->first();
    }

    public function update(ApprovalActivities $activity, array $data): ApprovalActivities
    {
        $activity->update($data);
        return $activity->fresh();
    }
}
