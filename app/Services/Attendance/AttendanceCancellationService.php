<?php

namespace App\Services\Attendance;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Attendance\AttendanceRequestCancellations;
use App\Models\Attendance\AttendanceRequests;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan pembatalan untuk cuti/izin yang SUDAH approved sepenuhnya - jalan lewat modul approval
 * terpisah (ApprovalModuleEnum::ATTENDANCE_REQUEST_CANCELLATION), bukan langsung ubah status.
 * Batal saat pengajuan masih in_review TIDAK lewat sini - reuse
 * ApprovalFlowService::advanceStep(..., 'cancelled') yang sudah ada, dipanggil langsung dari
 * AttendanceRequestDetail.
 */
class AttendanceCancellationService
{
    public function __construct(
        protected ApprovalSnapshotRepository $snapshotRepo,
        protected ApprovalCreationService $approvalCreationService,
        protected AttendaceReqService $attendaceReqService,
    ) {}

    public function requestCancellation(AttendanceRequests $record, User $requester, string $reason): AttendanceRequestCancellations
    {
        if (! in_array($record->type, ['leave', 'permission'], true)) {
            throw new \RuntimeException('Pembatalan setelah disetujui hanya berlaku untuk Cuti dan Izin.');
        }

        if ($record->status !== 'approved') {
            throw new \RuntimeException('Pengajuan ini belum/tidak dalam status disetujui.');
        }

        if ($record->user_id !== $requester->id) {
            throw new \RuntimeException('Hanya pemilik pengajuan yang bisa mengajukan pembatalan.');
        }

        if ($this->attendaceReqService->cancellationCutoffPassed($record)) {
            throw new \RuntimeException('Tanggal mulai pengajuan ini sudah lewat, tidak bisa dibatalkan lagi.');
        }

        if ($record->cancellationRequests()->where('status', 'in_review')->exists()) {
            throw new \RuntimeException('Sudah ada pengajuan pembatalan yang masih diproses untuk pengajuan ini.');
        }

        return DB::transaction(function () use ($record, $requester, $reason) {
            $cancellation = AttendanceRequestCancellations::create([
                'attendance_request_id' => $record->id,
                'company_id' => $requester->company_id,
                'requested_by' => $requester->id,
                'reason' => $reason,
                'status' => 'in_review',
            ]);

            $masterFlow = $this->snapshotRepo->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST_CANCELLATION->value,
                companyId: $requester->company_id,
                userId: $requester->id,
            );

            if (! $masterFlow) {
                throw new \RuntimeException(
                    'Tidak ada alur persetujuan aktif untuk pembatalan cuti/izin di perusahaan ini. Hubungi admin.'
                );
            }

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST_CANCELLATION->value,
                moduleId: $cancellation->id,
                flow: $masterFlow,
                createdBy: $requester->id,
                companyId: $requester->company_id,
                userId: $requester->id,
            );

            return $cancellation;
        });
    }
}
