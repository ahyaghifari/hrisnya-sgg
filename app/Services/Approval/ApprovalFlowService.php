<?php

namespace App\Services\Approval;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Task\TaskStatus;
use App\Models\Attendance\AttendanceRequestCancellations;
use App\Models\LMS\TrainingRequests;
use App\Models\Shift\ShiftSwapRequestCancellations;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
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
        protected AttendaceReqService $attendaceReqService,
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

            if ($snapshot->module_type === ApprovalModuleEnum::ATTENDANCE_REQUEST_CANCELLATION->value) {
                $this->resolveCancellationRequest($snapshot, $activity, $actorId, $actorName, $note, $action);

                return;
            }

            if ($snapshot->module_type === ApprovalModuleEnum::SHIFT_SWAP_REQUEST_CANCELLATION->value) {
                $this->resolveShiftSwapCancellationRequest($snapshot, $activity, $actorId, $actorName, $note, $action);

                return;
            }

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

                // Tukar Shift - jalur ini cuma dilewati SEBELUM approved final (masih in_review,
                // executeApprovedSwap() belum pernah jalan), jadi tidak ada jadwal yang perlu
                // direvert di sini - cuma tutup status pengajuannya. Batal SETELAH approved lewat
                // modul terpisah SHIFT_SWAP_REQUEST_CANCELLATION (resolveShiftSwapCancellationRequest()).
                if ($snapshot->module_type === ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value) {
                    ShiftSwapRequests::where('id', $snapshot->module_id)->update([
                        'status' => 'cancelled',
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

                    // Cuti & Izin Tidak Masuk yang disetujui otomatis ditandai Libur di roster
                    // shift (Detail Penjadwalan) - lihat docblock
                    // AttendaceReqService::syncApprovedAbsenceToRoster(). approved_by belum
                    // ke-refresh di objek ini, di-refresh dulu biar notes/log-nya benar.
                    $freshAttendanceRequest = $attendanceRequest->fresh(['leaveDetails', 'permissionDetails', 'user']);
                    $this->attendaceReqService->syncApprovedAbsenceToRoster($freshAttendanceRequest);

                    // Cuti & Izin Tidak Masuk/Sakit/Khusus yang disetujui otomatis dicatat di
                    // attendance_records - lihat docblock
                    // AttendaceReqService::syncApprovedAbsenceToAttendanceRecords().
                    $this->attendaceReqService->syncApprovedAbsenceToAttendanceRecords($freshAttendanceRequest);
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

        // Bekukan step 'supervisor' sama seperti saat snapshot dibuat, biar perbandingan
        // apple-to-apple (snapshot sudah menyimpan bentuk job_position). Non-throwing di sini:
        // ini cuma cek sinkron, jangan bikin lihat approval crash kalau jabatan pengaju hilang.
        $resolveSupervisor = fn ($step) => $this->snapshotRepo->resolveSupervisorPositionId(
            $snapshot->created_by,
            (int) ($step->supervisor_level ?? 1),
        );

        $masterSteps = $masterFlow->steps->map(function ($step) use ($resolveSupervisor) {
            return [
                'order' => $step->step_order,
                'scope_type' => $step->scope_type,
                'scope_ref_id' => $step->scope_ref_id,
                'approver_type' => $step->approver_type === 'supervisor' ? 'job_position' : $step->approver_type,
                'is_final_step' => $step->is_final ? 1 : 0,
                'approver_ref_id' => $step->approver_type === 'supervisor' ? $resolveSupervisor($step) : $step->approver_ref_id,
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

            // Bekukan step 'supervisor' jadi job_position (parent jabatan pengaju), sama seperti
            // ApprovalCreationService::createSnapshot. Non-throwing: sync ulang tidak boleh gagal
            // keras di tengah alur kalau jabatan pengaju sudah tidak ada.
            $resolveSupervisor = fn ($step) => $this->snapshotRepo->resolveSupervisorPositionId(
                $snapshot->created_by,
                (int) ($step->supervisor_level ?? 1),
            );

            $masterSteps = $masterFlow->steps->map(function ($step) use ($resolveSupervisor) {
                return [
                    'order' => $step->step_order,
                    'approver_type' => $step->approver_type === 'supervisor' ? 'job_position' : $step->approver_type,
                    'approver_ref_id' => $step->approver_type === 'supervisor' ? $resolveSupervisor($step) : $step->approver_ref_id,
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

    /**
     * Alur khusus modul ATTENDANCE_REQUEST_CANCELLATION (pembatalan cuti/izin yang sudah
     * approved) - dipanggil dari advanceStep() sebelum logic generik ATTENDANCE_REQUEST supaya
     * tidak numpang lewat blok yang mengasumsikan module_id = id attendance_requests.
     */
    private function resolveCancellationRequest($snapshot, $activity, string $actorId, string $actorName, ?string $note, string $action): void
    {
        $cancellation = AttendanceRequestCancellations::with('attendanceRequest')->find($snapshot->module_id);

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
            $this->activityRepo->update($activity, ['status' => 'rejected', 'acted_by' => $actorId, 'acted_at' => now(), 'note' => $note]);
            $this->snapshotRepo->update($snapshot, ['status' => 'rejected']);
            $cancellation?->update(['status' => 'rejected']);

            $this->historyRepo->create([
                'requestable_type' => $snapshot->module_type,
                'requestable_id' => $snapshot->module_id,
                'snapshot_id' => $snapshot->id,
                'activity_id' => $activity->id,
                'action' => 'completed',
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'note' => "Pengajuan pembatalan ditolak {$actorName}",
            ]);

            if ($cancellation?->requested_by) {
                User::find($cancellation->requested_by)?->notify(
                    new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'rejected', $actorName)
                );
            }

            return;
        }

        // action === 'approved'
        $next = $this->getNextApprover($snapshot);

        if ($next) {
            $this->activityRepo->update($activity, [
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

            $this->snapshotRepo->update($snapshot, ['current_step_order' => $next['step_order'], 'status' => 'in_review']);

            $snapshot->refresh();
            $nextApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
            if ($nextApprover && $cancellation) {
                $requesterName = User::find($cancellation->requested_by)?->name ?? 'Seseorang';
                $nextApprover->notify(new ApprovalNeedsAction($snapshot->module_type, $snapshot->module_id, $requesterName));
            }

            return;
        }

        // Step terakhir - pembatalan final disetujui, eksekusi ke pengajuan asli.
        $this->activityRepo->update($activity, ['status' => 'approved', 'acted_by' => $actorId, 'acted_at' => now()]);
        $this->snapshotRepo->update($snapshot, ['status' => 'approved']);
        $cancellation?->update(['status' => 'approved']);

        $originalRequest = $cancellation?->attendanceRequest;

        if ($originalRequest) {
            $this->attendanceReqRepository->updateAttendanceRequest($originalRequest, [
                'status' => AttendanceReqStatus::CANCELLED->value,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
            ]);

            $this->attendaceReqService->revertApprovedAbsenceSync($originalRequest);
        }

        $this->historyRepo->create([
            'requestable_type' => $snapshot->module_type,
            'requestable_id' => $snapshot->module_id,
            'snapshot_id' => $snapshot->id,
            'activity_id' => $activity->id,
            'action' => 'completed',
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'note' => "Pembatalan disetujui {$actorName}",
        ]);

        if ($cancellation?->requested_by) {
            User::find($cancellation->requested_by)?->notify(
                new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'cancelled', $actorName)
            );
        }
    }

    /**
     * Alur khusus modul SHIFT_SWAP_REQUEST_CANCELLATION (pembatalan tukar shift yang sudah
     * approved) - pola persis resolveCancellationRequest() di atas, bedanya eksekusi finalnya
     * memanggil ShiftSwapRequestService::revertApprovedSwap() (mengembalikan jadwal) bukan sync
     * absensi.
     */
    private function resolveShiftSwapCancellationRequest($snapshot, $activity, string $actorId, string $actorName, ?string $note, string $action): void
    {
        $cancellation = ShiftSwapRequestCancellations::with('shiftSwapRequest')->find($snapshot->module_id);

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
            $this->activityRepo->update($activity, ['status' => 'rejected', 'acted_by' => $actorId, 'acted_at' => now(), 'note' => $note]);
            $this->snapshotRepo->update($snapshot, ['status' => 'rejected']);
            $cancellation?->update(['status' => 'rejected']);

            $this->historyRepo->create([
                'requestable_type' => $snapshot->module_type,
                'requestable_id' => $snapshot->module_id,
                'snapshot_id' => $snapshot->id,
                'activity_id' => $activity->id,
                'action' => 'completed',
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'note' => "Pengajuan pembatalan ditolak {$actorName}",
            ]);

            if ($cancellation?->requested_by) {
                User::find($cancellation->requested_by)?->notify(
                    new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'rejected', $actorName)
                );
            }

            return;
        }

        // action === 'approved'
        $next = $this->getNextApprover($snapshot);

        if ($next) {
            $this->activityRepo->update($activity, [
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

            $this->snapshotRepo->update($snapshot, ['current_step_order' => $next['step_order'], 'status' => 'in_review']);

            $snapshot->refresh();
            $nextApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
            if ($nextApprover && $cancellation) {
                $requesterName = User::find($cancellation->requested_by)?->name ?? 'Seseorang';
                $nextApprover->notify(new ApprovalNeedsAction($snapshot->module_type, $snapshot->module_id, $requesterName));
            }

            return;
        }

        // Step terakhir - pembatalan final disetujui, kembalikan jadwal ke normal.
        $this->activityRepo->update($activity, ['status' => 'approved', 'acted_by' => $actorId, 'acted_at' => now()]);
        $this->snapshotRepo->update($snapshot, ['status' => 'approved']);
        $cancellation?->update(['status' => 'approved']);

        $originalRequest = $cancellation?->shiftSwapRequest;

        if ($originalRequest) {
            $originalRequest->update([
                'status' => 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
            ]);

            app(\App\Services\Shift\ShiftSwapRequestService::class)->revertApprovedSwap($originalRequest);
        }

        $this->historyRepo->create([
            'requestable_type' => $snapshot->module_type,
            'requestable_id' => $snapshot->module_id,
            'snapshot_id' => $snapshot->id,
            'activity_id' => $activity->id,
            'action' => 'completed',
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'note' => "Pembatalan disetujui {$actorName}",
        ]);

        if ($cancellation?->requested_by) {
            User::find($cancellation->requested_by)?->notify(
                new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'cancelled', $actorName)
            );
        }
    }
}
