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

    /** Skip approval flow sepenuhnya buat company yang KepegawaianSettingEnum::
     * SKIP_JOB_POSITION_APPROVAL diaktifkan - jabatan baru langsung approved & kuota awal
     * langsung aktif, TANPA bikin approval snapshot (beda dari approveJobPosition() yang
     * meng-advance snapshot yang sudah ada). */
    public function autoApproveJobPosition(JobPositions $jobPositions): object
    {
        $userId = Auth::user()->id;
        $plannedManPower = $jobPositions->planned_manpower ?? 0;

        $request = $this->jobPositionRepo->update($jobPositions, [
            'status' => \App\Enum\JobPositionReqStatus::APPROVED->value,
            'request_type' => 'create',
            'request_by' => $userId,
            'manpower' => $plannedManPower,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $request;
    }

    /**
     * $approvedQuantity = jumlah kuota yang DISETUJUI kali ini (ditambahkan ke `manpower`
     * yang sudah ada; 0 untuk penolakan).
     */
    public function approveJobPosition(
        JobPositions $jobPositions,
        string $status,
        int $approvedQuantity,
        string $note
    ): object
    {
        $userId = Auth::user()->id;

        $totalManpower = ($jobPositions->manpower ?? 0) + $approvedQuantity;

        $request = $this->jobPositionRepo->update($jobPositions, [
            'status' => $status,
            'manpower' => $totalManpower,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

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
        $manpower = $jobPosition->manpower ?? 0;
        $assignedUsersCount = $jobPosition->userAssignments()->count();

        return $assignedUsersCount < $manpower;
    }
}