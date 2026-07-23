<?php

namespace App\Services\JobPosition;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\JobPositionReqStatus;
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

    /** Dipakai portal — bikin record lalu, kalau $submit, langsung ajukan approval. */
    public function create(array $data, bool $submit): ManpowerRequests
    {
        $data['status'] = $submit ? JobPositionReqStatus::REQUESTED->value : JobPositionReqStatus::DRAFT->value;
        $data['requested_by'] = Auth::id();

        $manpowerRequest = $this->manpowerReqRepository->create($data);

        if ($submit) {
            $this->requestManPower($manpowerRequest);
        }

        return $manpowerRequest;
    }

    /** Jaga-jaga snapshot belum terbuat (draft yang baru diajukan ulang) + sinkronkan step kalau
     * master flow berubah selagi masih requested — pola sama dengan UserTransitionService. */
    public function ensureApprovalSnapshot(ManpowerRequests $manpowerRequests): void
    {
        if (! $manpowerRequests->approvalSnapshot) {
            return;
        }

        $inFinalState = in_array($manpowerRequests->status, [
            JobPositionReqStatus::APPROVED->value,
            JobPositionReqStatus::REJECTED->value,
        ], true);

        if (! $inFinalState && ! $this->approvalFlowService->checkStepsSync($manpowerRequests->approvalSnapshot)) {
            $this->approvalFlowService->syncStepsFromMaster($manpowerRequests->approvalSnapshot);
        }
    }

    public function reject(ManpowerRequests $manpowerRequests, string $note, string $actorId): void
    {
        $this->approvalFlowService->advanceStep(
            snapshot: ApprovalSnapshots::find($manpowerRequests->approvalSnapshot->id),
            actorId: $actorId,
            note: $note,
            action: JobPositionReqStatus::REJECTED->value,
        );

        $this->manpowerReqRepository->update($manpowerRequests, [
            'status' => JobPositionReqStatus::REJECTED->value,
        ]);
    }

    public function requestManPower(ManpowerRequests $manpowerRequests): object
    {
        $type = ApprovalModuleEnum::from($manpowerRequests->type . '_request')->value;

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
