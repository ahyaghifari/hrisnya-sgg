<?php

namespace App\Repositories\JobPosition;

use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;

class JobPositionRepositories
{
    public static function update(JobPositions $jobPosition, array $data): JobPositions
    {
        $jobPosition->update($data);
        return $jobPosition->refresh();
    }

    public static function updateOrgStrucuture(OrgStructure $orgStructure, array $data): OrgStructure
    {
        $orgStructure->update($data);
        return $orgStructure->refresh();
    }

    public function findJobPositionById(string $id): ?JobPositions
    {
        return JobPositions::find($id);
    }
}
