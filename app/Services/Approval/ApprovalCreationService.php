<?php

namespace App\Services\Approval;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\User;
use App\Notifications\ApprovalNeedsAction;
use App\Repositories\Approval\ApprovalActivityRepository;
use App\Repositories\Approval\ApprovalHistoryRepository;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;

class ApprovalCreationService
{
    public function __construct(
        protected ApprovalSnapshotRepository $snapshotRepo,
        protected ApprovalActivityRepository $activityRepo,
        protected ApprovalHistoryRepository $historyRepo,
        protected NotificationService $notificationService,
    ) {}

    public function createSnapshot(
        string $moduleType,
        string $moduleId,
        MasterApprovalFlows $flow,
        string $createdBy,
        ?string $companyId = null,
        string $userId
    ): ApprovalSnapshots {
        return DB::transaction(function () use ($moduleType, $moduleId, $flow, $createdBy, $companyId, $userId) {
            // Ambil steps dari master flow
            $steps = $flow->steps->map(fn($s) => [
                'order' => $s->step_order,
                'approver_type' => $s->approver_type,
                'approver_ref_id' => $s->approver_ref_id,
                'scope_type' => $s->scope_type,
                'scope_ref_id' => $s->scope_ref_id ?? null,
                'is_final_step' => $s->is_final,
            ])->values()->toArray();

            // Tentukan step pertama
            $firstStep = $steps[0] ?? null;
            // $nextStep = $steps[1] ?? null;

            $snapshot = $this->snapshotRepo->create([
                'module_type' => $moduleType,
                'module_id' => $moduleId,
                'flow_id' => $flow->id,
                'flow_name' => $flow->name,
                'flow_version' => $flow->version ?? 1,
                'steps_snapshot' => $steps,
                'status' => 'in_review',
                'current_step_order' => $firstStep['order'] ?? null,
                'company_id' => $companyId,
                'created_by' => $createdBy,
            ]);

            if ($firstStep) {
                $activity = $this->activityRepo->create([
                    'snapshot_id' => $snapshot->id,
                    'current_step_order' => $firstStep['order'],
                    'approver_type' => $firstStep['approver_type'],
                    'approver_ref_id' => $firstStep['approver_ref_id'],
                    'scope_type' => $firstStep['scope_type'] ?? null,
                    'scope_ref_id' => $firstStep['scope_ref_id'] ?? null,
                    'status' => 'pending',
                    'acted_by' => $userId,
                    'acted_at' => now(),
                ]);
            }

            $this->historyRepo->create([
                'requestable_type' => $moduleType,
                'requestable_id' => $moduleId,
                'snapshot_id' => $snapshot->id,
                'activity_id' => $activity->id ?? null,
                'action' => 'requested',
                'actor_id' => $createdBy,
                'actor_name' => User::find($createdBy)?->name,
                'note' => "User membuat pengajuan",
                'metadata' => [
                    'step_order' => 0,
                    'flow_name' => $flow->name,
                    'flow_version' => $flow->version ?? 1,
                ],
            ]);

            // Kabari approver step pertama - sebelumnya tidak ada notifikasi apa pun dikirim
            // saat pengajuan baru dibuat (cuma dikirim saat advanceStep ke step berikutnya),
            // jadi approver pertama baru tahu ada pengajuan kalau kebetulan buka listnya sendiri.
            // ATTENDANCE_REQUEST dikecualikan - AttendaceReqService sudah kirim notifikasi
            // submit sendiri (LeaveRequestSubmitted/PermissionRequestSubmitted/
            // OvertimeRequestSubmitted), jangan dobel.
            if ($firstStep && $moduleType !== ApprovalModuleEnum::ATTENDANCE_REQUEST->value) {
                $snapshot->refresh();
                $firstApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
                if ($firstApprover) {
                    $requesterName = User::find($createdBy)?->name ?? 'Seseorang';
                    $firstApprover->notify(new ApprovalNeedsAction($moduleType, $moduleId, $requesterName));
                }
            }

            return $snapshot;
        });
    }
}
