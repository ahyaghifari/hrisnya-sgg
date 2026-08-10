<?php

namespace App\Services\Approval;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\JobPositionReqStatus;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Job\ManpowerRequests;
use App\Models\LMS\TrainingRequests;
use App\Models\Master\JobPositions;
use App\Models\User\UserTransition;
use App\Services\JobPosition\JobPositionService;
use App\Services\JobPosition\ManPowerReqService;
use App\Services\User\UserTransitionService;

/**
 * "Approve Paksa" (admin panel, Admin/Super Admin saja) - lompati approver step saat ini,
 * langsung setujui atas nama admin (instruksi user 2026-08-10: kadang approver asli/Direktur
 * lambat merespons, admin perlu jalan pintas tanpa harus jadi approver yang sah dulu). BEDA
 * dari PendingApprovalsCard::approveOne() (portal, cuma bisa approve baris yang approver-nya
 * memang Auth::user() sendiri) - service ini sengaja TIDAK cek ApprovalPermissionService::
 * canApprove() sama sekali, itu justru maksud fitur ini (bypass).
 *
 * Cuma modul yang BENERAN punya alur approve aktif yang didukung (dicek satu-satu 2026-08-10) -
 * TASK_REQUEST/SWAP_SHIFT_REQUEST/APPROVAL_CANDIDATE_APPLICANT sengaja tidak dimasukkan,
 * enum-nya ada tapi tidak ada satupun halaman/service yang pernah memanggil advanceStep()
 * untuk 3 modul itu (orphan/belum pernah dibangun), approve paksa untuk modul yang approve
 * normalnya saja tidak ada jalurnya berisiko bikin state rusak.
 */
class ApprovalOverrideService
{
    public function __construct(
        protected ApprovalFlowService $approvalFlowService,
        protected JobPositionService $jobPositionService,
        protected ManPowerReqService $manPowerReqService,
        protected UserTransitionService $userTransitionService,
    ) {}

    /** @return array<int, string> */
    public function supportedModules(): array
    {
        return [
            ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            ApprovalModuleEnum::TRAINING_REQUEST->value,
            ApprovalModuleEnum::USER_TRANSITION->value,
            ApprovalModuleEnum::JOBPOSITION_REQUEST->value,
            ApprovalModuleEnum::ADDITIONAL_EMPLOYEE_REQUEST->value,
            ApprovalModuleEnum::EMPLOYEE_REPLACEMENT_REQUEST->value,
            ApprovalModuleEnum::ADDITIONAL_QUOTA_REQUEST->value,
        ];
    }

    public function approve(string $moduleType, string $moduleId, string $actorId, string $note): void
    {
        match ($moduleType) {
            ApprovalModuleEnum::ATTENDANCE_REQUEST->value => $this->approvalFlowService->advanceStep(
                AttendanceRequests::findOrFail($moduleId)->approvalSnapshot,
                $actorId,
                $note,
                'approved',
            ),
            ApprovalModuleEnum::TRAINING_REQUEST->value => $this->approvalFlowService->advanceStep(
                TrainingRequests::findOrFail($moduleId)->approvalSnapshot,
                $actorId,
                $note,
                'approved',
            ),
            ApprovalModuleEnum::USER_TRANSITION->value => $this->userTransitionService->approve(
                UserTransition::findOrFail($moduleId),
                $actorId,
                $note,
            ),
            // Realisasi kuota di-default ke jumlah yang diajukan (planned_manpower) - tidak ada
            // form input tambahan di approve paksa ini, sama seperti nilai default yang dipakai
            // EditJabatanPage::openApprovalModal() saat modal dibuka.
            ApprovalModuleEnum::JOBPOSITION_REQUEST->value => $this->approveJobPosition($moduleId, $note),
            ApprovalModuleEnum::ADDITIONAL_EMPLOYEE_REQUEST->value,
            ApprovalModuleEnum::EMPLOYEE_REPLACEMENT_REQUEST->value,
            ApprovalModuleEnum::ADDITIONAL_QUOTA_REQUEST->value => $this->manPowerReqService->approveManPower(
                manpowerRequests: ManpowerRequests::findOrFail($moduleId),
                status: JobPositionReqStatus::APPROVED->value,
                note: $note,
            ),
            default => throw new \RuntimeException("Modul \"{$moduleType}\" belum didukung approve paksa."),
        };
    }

    private function approveJobPosition(string $moduleId, string $note): void
    {
        $jobPosition = JobPositions::findOrFail($moduleId);

        $this->jobPositionService->approveJobPosition(
            jobPositions: $jobPosition,
            status: JobPositionReqStatus::APPROVED->value,
            actualManPower: (int) ($jobPosition->planned_manpower ?? 0),
            note: $note,
        );
    }
}
