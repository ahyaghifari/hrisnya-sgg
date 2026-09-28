<?php

namespace App\Services\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleRosterChangeRequest;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Support\Facades\DB;

/**
 * Kepala Ruangan (punya Update:ShiftScheduleAssignments tapi TIDAK Update:Schedule) tidak boleh
 * langsung menulis roster - simpan pengajuan dulu di sini, tulis beneran (lewat RosterBatchWriter,
 * writer yang sama dipakai simpan langsung) baru terjadi begitu disetujui atasannya
 * (ApprovalFlowService::advanceStep(), lihat cabang SCHEDULE_ROSTER_CHANGE). Approver default =
 * atasan pengaju 1 level (approver_type 'supervisor' di master flow modul ini, diatur admin lewat
 * /admin/approval-flows - instruksi user 2026-09-28: approval diisi default saja, tidak perlu
 * pengaju pilih approver).
 */
class ScheduleRosterChangeService
{
    public function __construct(
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected RosterBatchWriter $writer,
    ) {}

    /** @param array<int, array{user_id:string, date:string, before:?string, value:?string}> $pending */
    public function submitForApproval(Schedule $schedule, string $period, array $pending, User $user): ScheduleRosterChangeRequest
    {
        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value,
            companyId: $user->company_id,
            userId: $user->id,
        );

        if (! $masterFlow) {
            throw new \RuntimeException(
                'Tidak ada alur persetujuan aktif untuk perubahan jadwal shift di perusahaan ini. '
                . 'Minta admin membuat alur persetujuan default untuk modul ini terlebih dahulu.'
            );
        }

        return DB::transaction(function () use ($schedule, $period, $pending, $user, $masterFlow) {
            $request = ScheduleRosterChangeRequest::create([
                'company_id' => $schedule->company_id,
                'schedule_id' => $schedule->id,
                'period' => $period,
                'payload' => $pending,
                'requested_by' => $user->id,
                'status' => 'in_review',
            ]);

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value,
                moduleId: $request->id,
                flow: $masterFlow,
                createdBy: $user->id,
                companyId: $user->company_id,
                userId: $user->id,
            );

            return $request;
        });
    }

    /** Dipanggil ApprovalFlowService::advanceStep() saat step terakhir disetujui - actorId
     * dicatat sebagai changed_by penulis roster tetap pengaju (bukan approver), sel-sel ini
     * memang atas nama Kepala Ruangan, approver cuma menyetujui. */
    public function executeApproved(ScheduleRosterChangeRequest $request): void
    {
        $this->writer->write($request->schedule, $request->payload, $request->requested_by);
    }
}
