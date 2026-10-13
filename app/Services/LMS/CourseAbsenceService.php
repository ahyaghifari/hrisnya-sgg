<?php

namespace App\Services\LMS;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Approval\AttendanceApprovalBucketEnum;
use App\Models\LMS\LmsCourseAbsence;
use App\Models\LMS\LmsMeetings;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Support\Facades\DB;

class CourseAbsenceService
{
    public function __construct(
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
    ) {}

    public function store(LmsMeetings $meeting, User $user, string $reason): LmsCourseAbsence
    {
        // Alur izin Tidak Masuk berdasarkan jabatan dipakai apa adanya - tidak ada pengaturan alur terpisah.
        $flow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            companyId: $user->company_id,
            userId: $user->id,
            subModule: AttendanceApprovalBucketEnum::IZIN_TIDAK_MASUK->value,
        );

        if (! $flow) {
            throw new \RuntimeException('Tidak ada alur persetujuan izin tidak masuk yang aktif untuk jabatan Anda.');
        }

        return DB::transaction(function () use ($meeting, $user, $reason, $flow) {
            $absence = LmsCourseAbsence::create([
                'company_id' => $user->company_id,
                'course_id' => $meeting->course_id,
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'status' => 'in_review',
            ]);

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::TRAINING_ABSENCE->value,
                moduleId: $absence->id,
                flow: $flow,
                createdBy: $user->id,
                companyId: $user->company_id,
                userId: $user->id,
            );

            return $absence;
        });
    }
}
