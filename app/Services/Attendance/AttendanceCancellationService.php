<?php

namespace App\Services\Attendance;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\MasterApprovalFlows;
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
        if (! in_array($record->type, ['leave', 'permission', 'overtime'], true)) {
            throw new \RuntimeException('Pembatalan setelah disetujui hanya berlaku untuk Cuti, Izin, dan Lembur.');
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

            // Tidak ada alur pembatalan di master -> default: cukup approver PERTAMA dari alur
            // pengajuan aslinya (mis. cuti: Supervisor lalu Manager -> batal cukup Supervisor).
            $prependSteps = [];
            if (! $masterFlow) {
                $original = $record->approvalSnapshot;
                $first = collect($original?->steps_snapshot)->sortBy('order')->first();
                $originalFlow = $original ? MasterApprovalFlows::find($original->flow_id) : null;

                if (! $first || ! $originalFlow) {
                    throw new \RuntimeException(
                        'Tidak ada alur persetujuan pembatalan cuti/izin/lembur dan approver pengajuan asli tidak ditemukan. Hubungi admin.'
                    );
                }

                // Step sudah dibekukan di snapshot asli (supervisor sudah jadi job_position) - dipakai apa adanya.
                // Flow disalin TANPA steps (tidak disimpan) supaya hanya step pertama itu yang jadi alur.
                $masterFlow = $originalFlow->replicate();
                $masterFlow->id = $originalFlow->id;
                $masterFlow->name = 'Pembatalan (default: approver pertama pengajuan asli)';
                $masterFlow->setRelation('steps', collect());
                $prependSteps = [[
                    'approver_type' => $first['approver_type'],
                    'approver_ref_id' => $first['approver_ref_id'],
                    'scope_type' => $first['scope_type'] ?? null,
                    'scope_ref_id' => $first['scope_ref_id'] ?? null,
                ]];
            }

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST_CANCELLATION->value,
                moduleId: $cancellation->id,
                flow: $masterFlow,
                createdBy: $requester->id,
                companyId: $requester->company_id,
                userId: $requester->id,
                prependSteps: $prependSteps,
            );

            return $cancellation;
        });
    }
}
