<?php

namespace App\Services\JobPosition;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\JobPositions;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\JobPosition\JobPositionRepositories;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Support\Facades\Auth;

class JobPositionService
{
    public function __construct(
        protected JobPositionRepositories $jobPositionRepo,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalFlowService $approvalFlowService,
    ) {}
    
    public function requestJobPosition(JobPositions $jobPositions, string $status): object
    {
        $userId = Auth::user()->id;
        $isDraft = true;
        $requestType = 'create';
        $requestBy = $userId;

        $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            moduleType: ApprovalModuleEnum::JOBPOSITION_REQUEST->value,
            companyId: Auth::user()->company_id
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for job position requests for this company.');
        }

        $request = $this->jobPositionRepo->update($jobPositions, [
            'is_draft' => $isDraft,
            'status' => $status,
            'request_type' => $requestType,
            'request_by' => $requestBy,
        ]);

        $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::JOBPOSITION_REQUEST->value,
            moduleId: $jobPositions->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: Auth::user()->company_id,
            userId: $userId
        );

        return $request;
    }

    public function approveJobPosition(
        JobPositions $jobPositions, 
        string $status, 
        int $actualManPower, 
        string $note
    ): object
    {
        $userId = Auth::user()->id;
        $isDraft = false;

        $currentActualManPower = $jobPositions->actual_manpower ?? 0;
        $approvedManPower = $actualManPower;
        $totalActualManPower = $currentActualManPower + $approvedManPower;

        $request = $this->jobPositionRepo->update($jobPositions, [
            'is_draft' => $isDraft,
            'status' => $status,
            'approved_manpower' => $approvedManPower,
            'actual_manpower' => $totalActualManPower,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        if ($jobPositions->orgStructure) {
            $this->jobPositionRepo->updateOrgStrucuture($jobPositions->orgStructure, [
                'capacity' => $totalActualManPower,
            ]);
        }

        $this->approvalFlowService->advanceStep(
            snapshot: ApprovalSnapshots::find(
                $jobPositions->approvalSnapshots->last()->id
            ),
            actorId: $userId,
            note: $note,
            action: $status,
        );

        return $request;
    }

    public function canAddUserToJobPosition(JobPositions $jobPosition): bool
    {
        $actualManpower = $jobPosition->actual_manpower ?? 0;
        $assignedUsersCount = $jobPosition->userAssignments()->count();

        return $assignedUsersCount < $actualManpower;
    }
}