<?php

namespace App\Repositories\Master;

use App\Enum\OrgStructurLevel;
use App\Models\Master\OrgStructure;
use App\Models\User\UserProfiles;

class DepartmentRepository
{
    public function getOtherDepartmentsByUserId(string $userId)
    {
        $profile = UserProfiles::with(['orgStructure.company'])
            ->where('user_id', $userId)
            ->first();

        if (!$profile || !$profile->department) {
            return collect();
        }

        $userDepartment = $profile->department;
        $companyId = $userDepartment->company_id;

        return OrgStructure::where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->where('company_id', $companyId)
            ->where('id', '!=', $userDepartment->id)
            ->get();
    }

    public function getDepartmentById(string $id)
    {
        return OrgStructure::find($id);
    }
}
