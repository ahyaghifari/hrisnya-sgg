<?php

namespace App\Services\Sikartap;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Sikartap\Sikartap;
use App\Notifications\Sikartap\SikartapKeputusanDirekturFinal;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Support\Facades\Auth;

/**
 * Fase 4 — Keputusan Direktur, rewrite Tahap 5 detail_pengajuan.php/
 * detail_pengajuan_direktur.php + bulk_keputusan_direktur.php legacy.
 *
 * WAJIB lewat Approval Engine (bukan Filament action nulis status langsung, lihat
 * analisis § Approval Engine vs custom di rencana-implementasi-sikartap.md) - beda dari
 * verifikasi berkas/tes/wawancara/MCU yang cukup kolom polos (1 approver, checklist, bukan
 * chain approver berjenjang).
 */
class SikartapDirectorDecisionService
{
    public function __construct(
        protected ApprovalSnapshotRepository $snapshotRepo,
        protected ApprovalCreationService $creationService,
        protected ApprovalFlowService $flowService,
    ) {}

    /**
     * HRD mengajukan pengajuan yang sudah eligible (§ Sikartap::isEligibleForDirectorDecision())
     * ke alur approval Direktur. Flow di-resolve dari company + job_position PELAMAR (bukan HRD
     * yang mengklik tombol) - konsisten dengan makna "alur approval buat jabatan ini".
     */
    public function submitForDecision(Sikartap $sikartap): ApprovalSnapshots
    {
        if (! $sikartap->isEligibleForDirectorDecision()) {
            throw new \Exception('Pengajuan belum memenuhi syarat untuk diajukan ke Direktur.');
        }

        if ($sikartap->approvalSnapshot) {
            throw new \Exception('Pengajuan ini sudah diajukan ke Direktur sebelumnya.');
        }

        $companyId = $sikartap->user->company_id;

        $flow = $this->snapshotRepo->resolveApprovalFlowForUser(
            moduleType: ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value,
            companyId: $companyId,
            userId: $sikartap->user_id,
        );

        if (! $flow) {
            throw new \Exception('Belum ada alur approval Keputusan Direktur yang aktif untuk company ini.');
        }

        return $this->creationService->createSnapshot(
            moduleType: ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value,
            moduleId: $sikartap->id,
            flow: $flow,
            createdBy: Auth::id(),
            companyId: $companyId,
            userId: $sikartap->user_id,
        );
    }

    public function approve(Sikartap $sikartap, string $note): void
    {
        $this->flowService->advanceStep(
            snapshot: ApprovalSnapshots::find($sikartap->approvalSnapshot->id),
            actorId: Auth::id(),
            note: $note,
            action: 'approved',
        );

        // Cuma notify pelamar kalau ini step FINAL (status_keputusan_direktur ke-sync jadi
        // 'approved' oleh ApprovalFlowService::advanceStep()) - kalau masih ada approver
        // berikutnya di flow, belum ada keputusan buat dikabari.
        if ($sikartap->refresh()->status_keputusan_direktur === 'approved') {
            $sikartap->user?->notify(new SikartapKeputusanDirekturFinal($sikartap->id, lulus: true));
        }
    }

    public function reject(Sikartap $sikartap, string $note): void
    {
        $this->flowService->advanceStep(
            snapshot: ApprovalSnapshots::find($sikartap->approvalSnapshot->id),
            actorId: Auth::id(),
            note: $note,
            action: 'rejected',
        );

        // Reject di step manapun langsung mengakhiri flow (lihat ApprovalFlowService), jadi
        // selalu final - beda dari approve yang bisa masih lanjut ke step berikutnya.
        $sikartap->user?->notify(new SikartapKeputusanDirekturFinal($sikartap->id, lulus: false));
    }
}
