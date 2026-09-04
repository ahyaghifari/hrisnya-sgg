<?php

namespace App\Services\JobPosition;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Job\JobOpenings;
use App\Models\Job\ManpowerRequests;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\JobPosition\JobPositionRepositories;
use App\Repositories\JobPosition\ManPowerReqRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Support\Facades\Auth;

class ManPowerReqService
{
   public function __construct(
        protected JobPositionRepositories $jobPositionRepo,
        protected ManPowerReqRepository $manpowerReqRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalFlowService $approvalFlowService,
    ) {}

    /** Dipakai portal — bikin record lalu, kalau $submit, langsung ajukan approval. */
    public function create(array $data, bool $submit): ManpowerRequests
    {
        $data['status'] = $submit ? JobPositionReqStatus::REQUESTED->value : JobPositionReqStatus::DRAFT->value;
        $data['requested_by'] = Auth::id();

        $manpowerRequest = $this->manpowerReqRepository->create($data);

        if ($submit) {
            $this->requestManPower($manpowerRequest);
        }

        return $manpowerRequest;
    }

    /** Lanjutkan draft yang sudah ada (bukan bikin baris baru) - dipakai halaman yang bisa
     * "ketemu draft lama, tinggal diisi" (mis. CreateJabatanQuotaPage). */
    public function updateDraft(ManpowerRequests $manpowerRequest, array $data, bool $submit): ManpowerRequests
    {
        $data['status'] = $submit ? JobPositionReqStatus::REQUESTED->value : JobPositionReqStatus::DRAFT->value;

        $this->manpowerReqRepository->update($manpowerRequest, $data);
        $manpowerRequest->refresh();

        if ($submit) {
            $this->requestManPower($manpowerRequest);
        }

        return $manpowerRequest;
    }

    /** Jaga-jaga snapshot belum terbuat (draft yang baru diajukan ulang) + sinkronkan step kalau
     * master flow berubah selagi masih requested — pola sama dengan UserTransitionService. */
    public function ensureApprovalSnapshot(ManpowerRequests $manpowerRequests): void
    {
        if (! $manpowerRequests->approvalSnapshot) {
            return;
        }

        $inFinalState = in_array($manpowerRequests->status, [
            JobPositionReqStatus::APPROVED->value,
            JobPositionReqStatus::REJECTED->value,
        ], true);

        if (! $inFinalState && ! $this->approvalFlowService->checkStepsSync($manpowerRequests->approvalSnapshot)) {
            $this->approvalFlowService->syncStepsFromMaster($manpowerRequests->approvalSnapshot);
        }
    }

    public function reject(ManpowerRequests $manpowerRequests, string $note, string $actorId): void
    {
        $this->approvalFlowService->advanceStep(
            snapshot: ApprovalSnapshots::find($manpowerRequests->approvalSnapshot->id),
            actorId: $actorId,
            note: $note,
            action: JobPositionReqStatus::REJECTED->value,
        );

        $this->manpowerReqRepository->update($manpowerRequests, [
            'status' => JobPositionReqStatus::REJECTED->value,
        ]);
    }

    public function requestManPower(ManpowerRequests $manpowerRequests): object
    {
        $type = ApprovalModuleEnum::from($manpowerRequests->type . '_request')->value;

        $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            moduleType: $type,
            companyId: Auth::user()->company_id
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for job position requests for this company.');
        }

        $userId = Auth::user()->id;
        $this->approvalCreationService->createSnapshot(
            moduleType: $type,
            moduleId: $manpowerRequests->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: Auth::user()->company_id,
            userId: $userId
        );

        return $manpowerRequests;
    }

    /**
     * Efek nyata (nambah actual_manpower / bikin JobOpening) & status APPROVED cuma boleh
     * terjadi saat step approval yang BENERAN terakhir - bukan tiap kali ada yang klik Setuju
     * (2026-08-09, sebelumnya dua-duanya dieksekusi unconditionally di awal method, sebelum
     * advanceStep() sempat nentuin ini step terakhir atau bukan. Akibatnya: flow 2 step, approve
     * step 1 sudah bikin JobOpening + set status=approved padahal masih nunggu step 2 - approver
     * step 2 jadi tidak lihat tombol Setuju karena getCanApproveProperty() cek status===requested,
     * dan kalau step 2 tetap di-approve bakal bikin JobOpening DUPLIKAT).
     */
    public function approveManPower(
        ManpowerRequests $manpowerRequests,
        string $status,
        string $note
    ): object
    {
        $userId = Auth::user()->id;

        $snapshot = ApprovalSnapshots::find($manpowerRequests->approvalSnapshot->id);

        $this->approvalFlowService->advanceStep(
            snapshot: $snapshot,
            actorId: $userId,
            note: $note,
            action: $status,
        );

        $isFinalStep = $snapshot->refresh()->status === JobPositionReqStatus::APPROVED->value;

        if (! $isFinalStep) {
            return $manpowerRequests;
        }

        $jobPosition = $this->jobPositionRepo->findJobPositionById($manpowerRequests->job_position_id);

        if ($manpowerRequests->type === ManpowerRequestType::ADDITIONAL_QUOTA->value) {
            $this->jobPositionRepo->update($jobPosition, [
                'actual_manpower' => $jobPosition->actual_manpower + $manpowerRequests->quantity,
            ]);

            $this->bumpOpenRecruitmentsQuota($manpowerRequests->job_position_id, $manpowerRequests->quantity);
        } else if ($manpowerRequests->type === ManpowerRequestType::ADDITIONAL_EMPLOYEE->value || $manpowerRequests->type === ManpowerRequestType::EMPLOYEE_REPLACEMENT->value) {
            $this->manpowerReqRepository->createJobOpening([
                'company_id' => $manpowerRequests->company_id,
                'job_position_id' => $manpowerRequests->job_position_id,
                'quota' => $manpowerRequests->quantity,
                'is_active' => false,
                'description' => $jobPosition->description,
                'requirements' => $jobPosition->qualification,
            ]);
        }

        $this->manpowerReqRepository->update($manpowerRequests, [
            'status' => $status
        ]);

        return $manpowerRequests;
    }

    /**
     * Penambahan Kuota untuk jabatan yang lowongannya sedang berjalan / menunggu dibuka:
     * tambahkan kuota itu ke lowongan tsb + re-sync ke Rekrutmen — supaya rekrut yang mid-flight
     * tidak jadi under-scoped (2026-08-29). Lowongan yang sudah ditutup (opened_at terisi +
     * is_active false) TIDAK disentuh.
     */
    private function bumpOpenRecruitmentsQuota(string $jobPositionId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $openings = JobOpenings::where('job_position_id', $jobPositionId)
            ->where(fn ($q) => $q->whereNull('opened_at')->orWhere('is_active', true))
            ->get();

        foreach ($openings as $opening) {
            $opening->update(['quota' => $opening->quota + $quantity]);

            app(\App\Services\Rekrutmen\RecruitmentSync::class)->sync($opening->fresh());
        }
    }
}
