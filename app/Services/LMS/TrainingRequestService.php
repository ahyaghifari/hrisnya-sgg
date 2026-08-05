<?php

namespace App\Services\LMS;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\LMS\TrainingRequests;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrainingRequestService
{
    public function __construct(
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
    ) {}

    public function store(array $data): TrainingRequests
    {
        $user = Auth::user();

        return DB::transaction(function () use ($data, $user) {
            $trainingRequest = TrainingRequests::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'title' => $data['title'],
                'organizer' => $data['organizer'] ?? null,
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'status' => 'in_review',
            ]);

            $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::TRAINING_REQUEST->value,
                companyId: $user->company_id,
                userId: $user->id,
            );

            if (! $masterFlow) {
                throw new \RuntimeException(
                    'Tidak ada alur persetujuan aktif untuk pengajuan pelatihan di perusahaan ini.'
                );
            }

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::TRAINING_REQUEST->value,
                moduleId: $trainingRequest->id,
                flow: $masterFlow,
                createdBy: $user->id,
                companyId: $user->company_id,
                userId: $user->id,
            );

            return $trainingRequest;
        });
    }
}
