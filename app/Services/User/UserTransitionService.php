<?php

namespace App\Services\User;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User;
use App\Models\User\UserTransition;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;

/**
 * Logic Perubahan Status Karyawan (Promosi/Demosi/Mutasi/PHK) — dipakai BERSAMA oleh admin
 * Filament (CreateUserTransition/EditUserTransition) dan portal (Kepegawaian). Diekstrak dari
 * 2 Page class admin supaya tidak duplikat antara admin & portal — approve/reject harus
 * berperilaku identik di kedua tempat (termasuk efek sampingnya: ganti jabatan/role/nonaktifkan
 * user), bukan 2 implementasi terpisah yang bisa mencar seiring waktu.
 */
class UserTransitionService
{
    public function __construct(
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalFlowService $approvalFlowService,
    ) {}

    /**
     * @param array<string, mixed> $data field header (user_id, transition_type, transitionable_id,
     *   role_id, effective_date, reason, manager_comment, dst — sama seperti kolom UserTransition)
     * @param array<string, mixed> $detailData field evaluasi kinerja (UserTransitionDetail), cuma
     *   relevan untuk Promosi/Demosi
     * @param array<int, string> $attachmentPaths path file yang sudah diupload
     */
    public function create(array $data, array $detailData, array $attachmentPaths, User $actingUser): UserTransition
    {
        $targetUser = User::findOrFail($data['user_id']);

        $data['number'] = UserTransition::generateNumber($data['transition_type'], $actingUser->company);
        $data['company_id'] = $actingUser->company_id;
        $data['status'] = UserTransitionStatusEnum::PENDING->value;
        $data['created_by'] = $actingUser->id;

        // old_value = kondisi TARGET karyawan sebelum perubahan (buat riwayat/audit) — bukan
        // kondisi user yang membuat pengajuan.
        if (in_array($data['transition_type'], [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value,
        ], true)) {
            $data['transitionable_type'] = JobPositions::class;
            $data['old_value'] = $targetUser->profile?->jobPosition?->name;
        }

        if ($data['transition_type'] === UserTransitionTypeEnum::MUTATION->value) {
            $data['transitionable_type'] = OrgStructure::class;
            $data['old_value'] = $targetUser->profile?->orgStructure?->name;
        }

        $transition = UserTransition::create($data);

        if (! empty($detailData)) {
            $transition->detail()->create($detailData);
        }

        foreach ($attachmentPaths as $path) {
            $transition->attachments()->create(['file_path' => $path]);
        }

        $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
            companyId: $actingUser->company_id,
        );

        if (! $masterFlow) {
            throw new \Exception('Tidak ada approval flow aktif untuk perubahan status karyawan pada perusahaan ini.');
        }

        $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
            moduleId: $transition->id,
            flow: $masterFlow,
            createdBy: $actingUser->id,
            companyId: $actingUser->company_id,
            userId: $actingUser->id,
        );

        return $transition;
    }

    /** Buat approval snapshot kalau belum ada (jaga-jaga, sama seperti EditUserTransition::mount()
     * lama) — dan sinkronkan step kalau master flow berubah sementara masih pending. */
    public function ensureApprovalSnapshot(UserTransition $transition): void
    {
        if (! $transition->approvalSnapshot) {
            $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
                companyId: $transition->company_id,
                userId: $transition->created_by,
            );

            if ($masterFlow) {
                $this->approvalCreationService->createSnapshot(
                    moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
                    moduleId: $transition->id,
                    flow: $masterFlow,
                    createdBy: $transition->created_by,
                    companyId: $transition->company_id,
                    userId: $transition->created_by,
                );
                $transition->load('approvalSnapshot.activity');
            }

            return;
        }

        $inFinalState = in_array($transition->status, [
            UserTransitionStatusEnum::APPROVED->value,
            UserTransitionStatusEnum::REJECTED->value,
        ], true);

        if (! $inFinalState && ! $this->approvalFlowService->checkStepsSync($transition->approvalSnapshot)) {
            $this->approvalFlowService->syncStepsFromMaster($transition->approvalSnapshot);
            $transition->update(['is_flow_changed' => true]);
        }
    }

    public function approve(UserTransition $transition, string $actorId): void
    {
        $this->approvalFlowService->advanceStep($transition->approvalSnapshot, $actorId, null, 'approved');

        $transition->update([
            'status' => UserTransitionStatusEnum::APPROVED->value,
            'approved_at' => now(),
            'approved_by' => $actorId,
        ]);

        $this->applyTransitionChanges($transition);

        $transition->refresh();
    }

    public function reject(UserTransition $transition, string $actorId): void
    {
        $this->approvalFlowService->advanceStep($transition->approvalSnapshot, $actorId, null, 'rejected');

        $transition->update(['status' => UserTransitionStatusEnum::REJECTED->value]);
    }

    /** Efek nyata ke data karyawan setelah transisi di-approve — jabatan/role baru (Promosi/
     * Demosi), departemen baru (Mutasi), atau nonaktifkan user (PHK). */
    private function applyTransitionChanges(UserTransition $transition): void
    {
        $user = $transition->user;
        $type = $transition->transition_type;

        if (in_array($type, [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value,
        ], true)) {
            $currentPosition = $user->jobPositions()->whereNull('end_date')->first();

            if ($currentPosition) {
                $currentPosition->update(['end_date' => now(), 'status' => 'inactive']);
            }

            $user->jobPositions()->create([
                'job_position_id' => $transition->transitionable_id,
                'assigned_by' => $transition->approved_by,
                'start_date' => $transition->effective_date,
                'status' => 'active',
            ]);

            $jobPosition = JobPositions::find($transition->transitionable_id);

            $user->profile->update([
                'job_position_id' => $jobPosition->id,
                'pangkat_id' => $jobPosition->pangkat_id,
                'kelas_id' => $jobPosition->kelas_id,
            ]);

            if ($transition->role_id) {
                $user->syncRoles([$transition->role]);
            }

            return;
        }

        if ($type === UserTransitionTypeEnum::MUTATION->value) {
            $user->profile?->update(['org_structure_id' => $transition->transitionable_id]);

            return;
        }

        if ($type === UserTransitionTypeEnum::TERMINATION->value) {
            $user->update(['is_active' => false]);

            $user->profile->update([
                'job_position_id' => null,
                'pangkat_id' => null,
                'kelas_id' => null,
                'org_structure_id' => null,
                'employee_type_id' => null,
            ]);

            $user->jobPositions()->whereNull('end_date')->update([
                'end_date' => now(),
                'status' => 'inactive',
            ]);
        }
    }
}
