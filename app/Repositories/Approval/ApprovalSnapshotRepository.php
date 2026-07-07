<?php

namespace App\Repositories\Approval;

use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\JobPositions;
use App\Models\User\UserProfiles;

class ApprovalSnapshotRepository
{
    public function create(array $data): ApprovalSnapshots
    {
        return ApprovalSnapshots::create($data);
    }

    public function findByModule(string $moduleType, string $moduleId): ?ApprovalSnapshots
    {
        return ApprovalSnapshots::with('activity')->where([
            'module_type' => $moduleType,
            'module_id'   => $moduleId,
        ])->first();
    }

    public function update(ApprovalSnapshots $snapshot, array $data): ApprovalSnapshots
    {
        $snapshot->update($data);
        return $snapshot->fresh();
    }

    public function findMasterApprovalFlowByCompany(string $moduleType, string $companyId)
    {
        return MasterApprovalFlows::where('module', $moduleType)
            ->where('is_active', true)
            ->where('company_id', $companyId)
            ->first();
    }

    public function resolveApprovalFlowForUser(
        string $moduleType,
        string $companyId,
        string $userId
    ): ?MasterApprovalFlows {
        // Ambil jabatan user dari profil
        $jobPositionId = UserProfiles::where('user_id', $userId)
            ->value('job_position_id');

        // Cek apakah ada alur spesifik yang match dengan jabatan user
        if ($jobPositionId) {
            $specificFlow = MasterApprovalFlows::with('steps')
                ->where('module', $moduleType)
                ->where('company_id', $companyId)
                ->where('flow_type', 'specific')
                ->where('is_active', true)
                ->whereHas('assignments', function ($q) use ($jobPositionId) {
                    $q->where('assignable_type', JobPositions::class)
                      ->where('assignable_id', $jobPositionId);
                })
                ->first();

            if ($specificFlow) {
                return $specificFlow;
            }
        }

        // Fallback ke alur default
        return MasterApprovalFlows::with('steps')
            ->where('module', $moduleType)
            ->where('company_id', $companyId)
            ->where('flow_type', 'default')
            ->where('is_active', true)
            ->first();
    }
}
