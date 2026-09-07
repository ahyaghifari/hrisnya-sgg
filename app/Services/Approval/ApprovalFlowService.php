<?php

namespace App\Services\Approval;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Task\TaskStatus;
use App\Models\LMS\TrainingRequests;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Services\Shift\ShiftSwapRequestService;
use App\Notifications\ApprovalNeedsAction;
use App\Notifications\RequestStatusChanged;
use App\Repositories\Approval\ApprovalActivityRepository;
use App\Repositories\Approval\ApprovalHistoryRepository;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Attendance\AttendanceReqRepository;
use App\Repositories\Task\TaskRepository;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalFlowService
{
    public function __construct(
        protected ApprovalSnapshotRepository $snapshotRepo,
        protected ApprovalActivityRepository $activityRepo,
        protected ApprovalHistoryRepository $historyRepo,
        protected AttendanceReqRepository $attendanceReqRepository,
        protected TaskRepository $taskRepository,
        protected NotificationService $notificationService,
    ) {}

    public function getNextApprover($snapshot): ?array
    {
        $steps = collect($snapshot->steps_snapshot);
        $currentOrder = $snapshot->current_step_order;
        $next = $steps->firstWhere('order', $currentOrder + 1);

        return $next ? [
            'step_order' => $next['order'],
            'type' => $next['approver_type'],
            'ref_id' => $next['approver_ref_id'],
            'scope_type' => $next['scope_type'] ?? null,
            'scope_ref_id' => $next['scope_ref_id'] ?? null,
        ] : null;
    }

    public function advanceStep($snapshot, string $actorId, ?string $note = null, string $action, array $additionalData = []): void
    {
        DB::transaction(function () use ($snapshot, $actorId, $note, $action, $additionalData) {
            $snapshot->refresh();
            $activity = $snapshot->activity;
            $activity->refresh();

            $actorName = User::find($actorId)?->name ?? 'Seseorang';

            // Log step sekarang
            $this->historyRepo->create([
                'requestable_type' => $snapshot->module_type,
                'requestable_id' => $snapshot->module_id,
                'snapshot_id' => $snapshot->id,
                'activity_id' => $activity->id,
                'action' => $action,
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'note' => $note,
                'metadata' => [
                    'step_order' => $activity->current_step_order,
                    'approver_type' => $activity->approver_type,
                ],
            ]);

            if ($action === 'rejected') {
                $activity = $this->activityRepo->update($activity, [
                    'status' => 'rejected',
                    'acted_by' => $actorId,
                    'acted_at' => now(),
                    'note' => $note,
                ]);

                $snapshot = $this->snapshotRepo->update($snapshot, ['status' => 'rejected']);

                $attendanceRequest = $this->attendanceReqRepository->findAttendanceRequestById($snapshot->module_id);
                if ($attendanceRequest) {
                    $this->attendanceReqRepository->updateAttendanceRequest($attendanceRequest, [
                        'status' => AttendanceReqStatus::REJECTED->value,
                        'rejected_by' => $actorId,
                        'rejected_at' => now(),
                    ]);
                }

                //Task
                if ($snapshot->module_type === ApprovalModuleEnum::TASK_REQUEST->value) {
                    $taskRequest = $this->taskRepository->findTaskById($snapshot->module_id);
                    if ($taskRequest) {
                        // Status kerja task (TaskStatus) tidak punya case REJECTED lagi -
                        // "ditolak" cukup direpresentasikan lewat approvalSnapshot->status di atas.
                        $this->taskRepository->update($snapshot->module_id, [
                            'responded_date' => now(),
                        ]);
                    }
                }

                // Shift Swap Request
                if ($snapshot->module_type === ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value) {
                    ShiftSwapRequests::where('id', $snapshot->module_id)->update([
                        'status' => 'rejected',
                        'rejected_by' => $actorId,
                        'rejected_at' => now(),
                    ]);
                }

                // Training Request
                if ($snapshot->module_type === ApprovalModuleEnum::TRAINING_REQUEST->value) {
                    TrainingRequests::where('id', $snapshot->module_id)->update([
                        'status' => 'rejected',
                        'rejected_by' => $actorId,
                        'rejected_at' => now(),
                    ]);
                }

                // Sikartap - Keputusan Direktur (Fase 4). Kolom status_keputusan_direktur cuma
                // cache tersinkron dari snapshot approval, bukan ditulis manual dari Filament
                // action - satu sumber kebenaran ada di approval_snapshots/approval_activities.
                if ($snapshot->module_type === ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value) {
                    Sikartap::where('id', $snapshot->module_id)->update([
                        'status_keputusan_direktur' => 'rejected',
                    ]);
                }

                $this->historyRepo->create([
                    'requestable_type' => $snapshot->module_type,
                    'requestable_id' => $snapshot->module_id,
                    'snapshot_id' => $snapshot->id,
                    'activity_id' => $activity->id,
                    'action' => 'completed',
                    'actor_id' => $actorId,
                    'actor_name' => $actorName,
                    'note' => "Pengajuan ditolak {$actorName}",
                ]);

                User::find($snapshot->created_by)?->notify(
                    new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'rejected', $actorName)
                );

                return;
            }

            if ($action === 'cancelled') {
                $activity = $this->activityRepo->update($activity, [
                    'status' => 'cancelled',
                    'acted_by' => $actorId,
                    'acted_at' => now(),
                    'note' => $note,
                ]);

                $snapshot = $this->snapshotRepo->update($snapshot, ['status' => 'cancelled']);

                $attendanceRequest = $this->attendanceReqRepository->findAttendanceRequestById($snapshot->module_id);
                if ($attendanceRequest) {
                    $this->attendanceReqRepository->updateAttendanceRequest($attendanceRequest, [
                        'status' => AttendanceReqStatus::CANCELLED->value,
                        'cancelled_by' => $actorId,
                        'cancelled_at' => now(),
                    ]);
                }

                $this->historyRepo->create([
                    'requestable_type' => $snapshot->module_type,
                    'requestable_id' => $snapshot->module_id,
                    'snapshot_id' => $snapshot->id,
                    'activity_id' => $activity->id,
                    'action' => 'completed',
                    'actor_id' => $actorId,
                    'actor_name' => $actorName,
                    'note' => "Pengajuan dibatalkan oleh {$actorName}",
                ]);

                User::find($snapshot->created_by)?->notify(
                    new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'cancelled', $actorName)
                );

                return;
            }

            $next = $this->getNextApprover($snapshot);

            if ($next) {
                $activity = $this->activityRepo->update($activity, [
                    'current_step_order' => $next['step_order'],
                    'approver_type' => $next['type'],
                    'approver_ref_id' => $next['ref_id'],
                    'scope_type' => $next['scope_type'] ?? null,
                    'scope_ref_id' => $next['scope_ref_id'] ?? null,
                    'status' => 'pending',
                    'note' => null,
                    'acted_by' => $actorId,
                    'acted_at' => now(),
                ]);

                $snapshot = $this->snapshotRepo->update($snapshot, [
                    'current_step_order' => $next['step_order'],
                    'status' => 'in_review',
                ]);

                // Kabari approver step berikutnya - snapshot->activity di-refresh dulu biar
                // getFirstApproverFromSnapshot() resolve dari data step BARU (bukan step lama
                // yang baru saja selesai), meski namanya "First" method ini generik: baca
                // activity apa adanya saat dipanggil.
                $snapshot->refresh();
                $nextApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
                if ($nextApprover) {
                    $requesterName = User::find($snapshot->created_by)?->name ?? 'Seseorang';
                    $nextApprover->notify(new ApprovalNeedsAction($snapshot->module_type, $snapshot->module_id, $requesterName));
                }

            } else {
                $activity = $this->activityRepo->update($activity, [
                    'status' => 'approved',
                    'acted_by' => $actorId,
                    'acted_at' => now(),
                ]);

                $snapshot = $this->snapshotRepo->update($snapshot, ['status' => 'approved']);

                $attendanceRequest = $this->attendanceReqRepository->findAttendanceRequestById($snapshot->module_id);
                if ($attendanceRequest) {
                    $this->attendanceReqRepository->updateAttendanceRequest($attendanceRequest, [
                        'status' => AttendanceReqStatus::APPROVED->value,
                        'approved_by' => $actorId,
                        'approved_at' => now(),
                    ]);
                }

                //Task
                if ($snapshot->module_type === ApprovalModuleEnum::TASK_REQUEST->value) {
                    $taskRequest = $this->taskRepository->findTaskById($snapshot->module_id);
                    if ($taskRequest) {
                        $this->taskRepository->update($snapshot->module_id, [
                            'assigned_to' => $additionalData['assigned_to'],
                            'due_date' => $additionalData['due_date'],
                            'status' => TaskStatus::TODO->value,
                            'responded_date' => now(),
                            'approved_by' => Auth::id(),
                        ]);
                    }
                }

                // Shift Swap Request
                if ($snapshot->module_type === ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value) {
                    $swapRequest = ShiftSwapRequests::with(['reqSchedule', 'targetSchedule'])
                        ->find($snapshot->module_id);
                    if ($swapRequest) {
                        $swapRequest->update([
                            'status' => 'approved',
                            'approved_by' => $actorId,
                            'approved_at' => now(),
                        ]);

                        // Eksekusi beneran (termasuk re-cek konflik & fallback Extra Shift buat
                        // tukar beda tanggal) dipindah ke ShiftSwapRequestService::executeApprovedSwap()
                        // - satu sumber kebenaran yang sama juga dipakai buat validasi pas
                        // pengajuan (lihat ShiftSwapRequestService::validateScheduleSwapRules()).
                        app(ShiftSwapRequestService::class)->executeApprovedSwap($swapRequest);
                    }
                }

                // Training Request
                if ($snapshot->module_type === ApprovalModuleEnum::TRAINING_REQUEST->value) {
                    TrainingRequests::where('id', $snapshot->module_id)->update([
                        'status' => 'approved',
                        'approved_by' => $actorId,
                        'approved_at' => now(),
                    ]);
                }

                // Sikartap - Keputusan Direktur (Fase 4), lihat catatan cache di branch reject di atas.
                if ($snapshot->module_type === ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value) {
                    Sikartap::where('id', $snapshot->module_id)->update([
                        'status_keputusan_direktur' => 'approved',
                    ]);

                    $this->markUserPermanent($snapshot->module_id);
                }

                $this->historyRepo->create([
                    'requestable_type' => $snapshot->module_type,
                    'requestable_id' => $snapshot->module_id,
                    'snapshot_id' => $snapshot->id,
                    'activity_id' => $activity->id,
                    'action' => 'completed',
                    'actor_id' => $actorId,
                    'actor_name' => $actorName,
                    'note' => "Pengajuan disetujui {$actorName}",
                ]);

                User::find($snapshot->created_by)?->notify(
                    new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'approved', $actorName)
                );
            }
        });
    }

    /**
     * Sikartap Keputusan Direktur final "approved" = karyawan resmi diangkat jadi Tetap.
     * Stempel user_profiles.tmt_tetap (TMT = tanggal keputusan ini) + flip employee_type_id
     * ke tipe kategori Tetap company itu KALAU cuma ada satu — kalau company punya >1 tipe
     * Tetap (atau nol), flip-nya diserahkan ke HR manual, tapi tmt_tetap tetap tercatat.
     */
    private function markUserPermanent(string $sikartapId): void
    {
        $sikartap = Sikartap::with('user.profile')->find($sikartapId);
        $profile = $sikartap?->user?->profile;

        if (! $profile) {
            return;
        }

        $updates = ['tmt_tetap' => now()->toDateString()];

        $tetapTypeIds = \App\Models\Master\EmployeeTypes::query()
            ->where('company_id', $sikartap->user->company_id)
            ->where('category', \App\Enum\EmployeeTypeCategory::TETAP->value)
            ->pluck('id');

        if ($tetapTypeIds->count() === 1) {
            $updates['employee_type_id'] = $tetapTypeIds->first();
        }

        $profile->update($updates);
    }

    public function checkStepsSync($snapshot): bool
    {
        // Resolve flow yang sesuai untuk user pengaju (spesifik atau default)
        $masterFlow = $this->snapshotRepo->resolveApprovalFlowForUser(
            moduleType: $snapshot->module_type,
            companyId: $snapshot->company_id,
            userId: $snapshot->created_by
        );
        
        if (!$masterFlow) {
            return false;
        }

        $currentSteps = collect($snapshot->steps_snapshot ?? []);
        
        $masterSteps = $masterFlow->steps->map(function ($step) {
            return [
                'order' => $step->step_order,
                'scope_type' => $step->scope_type,
                'scope_ref_id' => $step->scope_ref_id,
                'approver_type' => $step->approver_type,
                'is_final_step' => $step->is_final ? 1 : 0,
                'approver_ref_id' => $step->approver_ref_id,
            ];
        })->values()->toArray();

        if ($currentSteps->count() !== count($masterSteps)) {
            return false;
        }

        foreach ($masterSteps as $masterStep) {
            $currentStep = $currentSteps->firstWhere('order', $masterStep['order']);
            
            if (!$currentStep) {
                return false;
            }

            $fieldsToCompare = ['scope_type', 'scope_ref_id', 'approver_type', 'is_final_step', 'approver_ref_id'];
            foreach ($fieldsToCompare as $field) {
                if (isset($currentStep[$field]) && isset($masterStep[$field])) {
                    if ($currentStep[$field] != $masterStep[$field]) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function syncStepsFromMaster($snapshot): bool
    {
        return DB::transaction(function () use ($snapshot) {
            // Resolve flow yang sesuai untuk user pengaju (spesifik atau default)
            $masterFlow = $this->snapshotRepo->resolveApprovalFlowForUser(
                moduleType: $snapshot->module_type,
                companyId: $snapshot->company_id,
                userId: $snapshot->created_by
            );
            
            if (!$masterFlow) {
                return false;
            }

            $masterSteps = $masterFlow->steps->map(function ($step) {
                return [
                    'order' => $step->step_order,
                    'approver_type' => $step->approver_type,
                    'approver_ref_id' => $step->approver_ref_id,
                    'scope_type' => $step->scope_type,
                    'scope_ref_id' => $step->scope_ref_id ?? null,
                    'is_final_step' => $step->is_final,
                ];
            })->values()->toArray();

            $firstStep = $masterSteps[0] ?? null;

            if (!$firstStep) {
                return false;
            }

            $this->snapshotRepo->update($snapshot, [
                'steps_snapshot' => $masterSteps,
                'flow_id' => $masterFlow->id,
                'flow_name' => $masterFlow->name,
                'current_step_order' => $firstStep['order'],
                'status' => 'in_review',
            ]);

            $activity = $snapshot->activity;
            $actorName = User::find($snapshot->created_by)?->name;

            if ($activity) {
                $this->activityRepo->update($activity, [
                    'current_step_order' => $firstStep['order'],
                    'approver_type' => $firstStep['approver_type'],
                    'approver_ref_id' => $firstStep['approver_ref_id'],
                    'scope_type' => $firstStep['scope_type'] ?? null,
                    'scope_ref_id' => $firstStep['scope_ref_id'] ?? null,
                    'status' => 'pending',
                    'acted_by' => $snapshot->created_by,
                    'acted_at' => now(),
                    'note' => null,
                ]);

                $this->historyRepo->create([
                    'requestable_type' => $snapshot->module_type,
                    'requestable_id' => $snapshot->module_id,
                    'snapshot_id' => $snapshot->id,
                    'activity_id' => $activity->id,
                    'action' => 'flow_updated',
                    'actor_id' => $snapshot->created_by,
                    'actor_name' => $actorName,
                    'note' => "Alur persetujuan disinkronkan ulang dari master flow, karena data master flow diubah oleh {$actorName}",
                    'metadata' => [
                        'flow_name' => $masterFlow->name,
                        'flow_id' => $masterFlow->id,
                        'total_steps' => count($masterSteps),
                    ],
                ]);
            } else {
                // Activity tidak ditemukan, buat activity baru
                $activity = $this->activityRepo->create([
                    'snapshot_id' => $snapshot->id,
                    'current_step_order' => $firstStep['order'],
                    'approver_type' => $firstStep['approver_type'],
                    'approver_ref_id' => $firstStep['approver_ref_id'],
                    'scope_type' => $firstStep['scope_type'] ?? null,
                    'scope_ref_id' => $firstStep['scope_ref_id'] ?? null,
                    'status' => 'pending',
                    'acted_by' => $snapshot->created_by,
                    'acted_at' => now(),
                ]);

                $this->historyRepo->create([
                    'requestable_type' => $snapshot->module_type,
                    'requestable_id' => $snapshot->module_id,
                    'snapshot_id' => $snapshot->id,
                    'activity_id' => $activity->id,
                    'action' => 'activity_restored',
                    'actor_id' => $snapshot->created_by,
                    'actor_name' => $actorName,
                    'note' => "Activity approval dibuat ulang karena tidak ditemukan pada snapshot, disinkronkan dari master flow oleh {$actorName}",
                    'metadata' => [
                        'flow_name' => $masterFlow->name,
                        'flow_id' => $masterFlow->id,
                        'total_steps' => count($masterSteps),
                    ],
                ]);
            }

            return true;
        });
    }
}
