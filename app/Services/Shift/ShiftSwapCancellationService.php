<?php

namespace App\Services\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Shift\ShiftSwapRequestCancellations;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan pembatalan untuk tukar shift yang SUDAH approved sepenuhnya - jalan lewat modul
 * approval terpisah (ApprovalModuleEnum::SHIFT_SWAP_REQUEST_CANCELLATION), pola persis
 * App\Services\Attendance\AttendanceCancellationService. Batal saat pengajuan masih in_review
 * TIDAK lewat sini - reuse ApprovalFlowService::advanceStep(..., 'cancelled') yang sudah ada,
 * dipanggil langsung dari ShiftSwapRequestDetail.
 */
class ShiftSwapCancellationService
{
    public function __construct(
        protected ApprovalSnapshotRepository $snapshotRepo,
        protected ApprovalCreationService $approvalCreationService,
        protected ShiftSwapRequestService $shiftSwapRequestService,
    ) {}

    public function requestCancellation(ShiftSwapRequests $record, User $requester, string $reason): ShiftSwapRequestCancellations
    {
        if ($record->status !== 'approved') {
            throw new \RuntimeException('Pengajuan ini belum/tidak dalam status disetujui.');
        }

        if ($record->requester_id !== $requester->id) {
            throw new \RuntimeException('Hanya pemohon asli yang bisa mengajukan pembatalan.');
        }

        if ($this->shiftSwapRequestService->cancellationCutoffPassed($record)) {
            throw new \RuntimeException('Salah satu tanggal shift yang ditukar sudah lewat, tidak bisa dibatalkan lagi.');
        }

        if ($record->cancellationRequests()->where('status', 'in_review')->exists()) {
            throw new \RuntimeException('Sudah ada pengajuan pembatalan yang masih diproses untuk pengajuan ini.');
        }

        return DB::transaction(function () use ($record, $requester, $reason) {
            $cancellation = ShiftSwapRequestCancellations::create([
                'shift_swap_request_id' => $record->id,
                'company_id' => $requester->company_id,
                'requested_by' => $requester->id,
                'reason' => $reason,
                'status' => 'in_review',
            ]);

            $record->loadMissing('reqSchedule.shiftType.schedule');
            $headUserId = $record->reqSchedule?->shiftType?->schedule?->head_user_id;

            // Approver = Kepala Ruangan jadwal shift (Schedule::head_user_id), tanpa alur master
            // khusus pembatalan. Flow tukar shift cuma dipinjam sebagai pembawa flow_id (kolom
            // wajib); step-nya dikosongkan. Kalau pemohon sendiri kepala ruangannya (tidak boleh
            // approve giliran sendiri), step master flow tukar shift dipakai apa adanya.
            $masterFlow = $this->snapshotRepo->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
                companyId: $requester->company_id,
                userId: $requester->id,
            );

            if (! $masterFlow) {
                throw new \RuntimeException(
                    'Tidak ada alur persetujuan aktif untuk tukar shift di perusahaan ini. Hubungi admin.'
                );
            }

            $prependSteps = [];

            if ($headUserId && $headUserId !== $requester->id) {
                $masterFlow->setRelation('steps', collect());
                $prependSteps = [['approver_type' => 'user', 'approver_ref_id' => $headUserId]];
            }

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::SHIFT_SWAP_REQUEST_CANCELLATION->value,
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
