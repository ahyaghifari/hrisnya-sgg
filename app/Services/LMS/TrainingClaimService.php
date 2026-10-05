<?php

namespace App\Services\LMS;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\LMS\TrainingClaim;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan pelatihan yang SUDAH terlaksana. JP diisi saran sistem (total menit / menit_1_jp),
 * approver berpermission KELOLA_KLAIM_PELATIHAN boleh mengoreksinya sebelum approve final
 * (lihat TrainingClaimDetail). Approve/reject final di-sync ke status klaim oleh ApprovalFlowService.
 */
class TrainingClaimService
{
    public function __construct(
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
    ) {}

    public function store(array $data, UploadedFile $certificate): TrainingClaim
    {
        $user = Auth::user();

        return DB::transaction(function () use ($data, $certificate, $user) {
            $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::TRAINING_CLAIM->value,
                companyId: $user->company_id,
                userId: $user->id,
            );

            if (! $masterFlow) {
                throw new \RuntimeException(
                    'Tidak ada alur persetujuan aktif untuk pengajuan pelatihan terlaksana di perusahaan ini.'
                );
            }

            $claim = TrainingClaim::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'title' => $data['title'],
                'organizer' => $data['organizer'] ?? null,
                'certificate_path' => $certificate->store('training-claims/' . $user->id, 'public'),
                'status' => 'pending',
            ]);

            $claim->sessions()->createMany($data['sessions']);
            $claim->update(['jp' => TrainingClaim::jpFor(TrainingClaim::minutesOf($claim->sessions))]);

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::TRAINING_CLAIM->value,
                moduleId: $claim->id,
                flow: $masterFlow,
                createdBy: $user->id,
                companyId: $user->company_id,
                userId: $user->id,
            );

            return $claim;
        });
    }
}
