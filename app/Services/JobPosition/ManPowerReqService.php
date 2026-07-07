<?php

namespace App\Services\JobPosition;

use App\Enum\ManpowerRequestType;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Job\ManpowerRequests;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\JobPosition\JobPositionRepositories;
use App\Repositories\JobPosition\ManPowerReqRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Support\Facades\Auth;

class ManPowerReqService
{
   public function __construct(
        protected JobPositionRepositories $jobPositionRepo,
        protected ManPowerReqRepository $manpowerReqRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalFlowService $approvalFlowService,
    ) {}
    
    public function requestManPower(ManpowerRequests $manpowerRequests): object
    {
        $type = $manpowerRequests->type . '_request';

        $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            moduleType: $type,
            companyId: Auth::user()->company_id
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for job position requests for this company.');
        }

        $userId = Auth::user()->id;
        $this->approvalCreationService->createSnapshot(
            moduleType: $type,
            moduleId: $manpowerRequests->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: Auth::user()->company_id,
            userId: $userId
        );

        return $manpowerRequests;
    }

    public function approveManPower(
        ManpowerRequests $manpowerRequests, 
        string $status,
        string $note
    ): object
    {
        $userId = Auth::user()->id;
        $jobPosition = $this->jobPositionRepo->findJobPositionById($manpowerRequests->job_position_id);

        if ($manpowerRequests->type === ManpowerRequestType::ADDITIONAL_QUOTA->value) {
            $jobPosition = $this->jobPositionRepo->update($jobPosition, [
                'actual_manpower' => $jobPosition->actual_manpower + $manpowerRequests->quantity,
            ]);
        } else if ($manpowerRequests->type === ManpowerRequestType::ADDITIONAL_EMPLOYEE->value || $manpowerRequests->type === ManpowerRequestType::EMPLOYEE_REPLACEMENT->value) {
            $this->manpowerReqRepository->createJobOpening([
                'company_id' => $manpowerRequests->company_id,
                'job_position_id' => $manpowerRequests->job_position_id,
                'quota' => $manpowerRequests->quantity,
                'is_active' => false,
                'description' => $jobPosition->description,
                'requirements' => $jobPosition->qualification,
            ]);
        }

        $this->approvalFlowService->advanceStep(
            snapshot: ApprovalSnapshots::find(
                $manpowerRequests->approvalSnapshot->id
            ),
            actorId: $userId,
            note: $note,
            action: $status,
        );

        $this->manpowerReqRepository->update($manpowerRequests, [
            'status' => $status
        ]);

        return $manpowerRequests;
    }
}
