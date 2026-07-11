<?php

namespace App\Filament\Resources\UserTransitions\Pages;

use App\Enum\CoachingStatus;
use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Filament\Resources\UserTransitions\UserTransitionResource;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User\UserTransition;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUserTransition extends CreateRecord
{
    protected static string $resource = UserTransitionResource::class;

    protected array $detailData = [];
    protected array $attachmentData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $auth = auth()->user();

        $data['number'] = UserTransition::generateNumber($data['transition_type'], $auth->company);
        $data['company_id'] = $auth->company_id;
        $data['status'] = UserTransitionStatusEnum::PENDING->value;

        $this->detailData = $data['detail'] ?? [];
        unset($data['detail']);

        $this->attachmentData = $data['attachments'] ?? [];
        unset($data['attachments']);

        if (in_array($data['transition_type'], [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value
        ])) {
            $data['transitionable_type'] = JobPositions::class;
            $data['old_value'] = auth()->user()->profile?->jobPosition?->name ?? null;
        }

        if ($data['transition_type'] === UserTransitionTypeEnum::MUTATION->value) {
            $data['transitionable_type'] = OrgStructure::class;
            $data['old_value'] = auth()->user()->company?->name ?? null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (!empty($this->detailData)) {
            $this->record->detail()->create($this->detailData);
        }

        $approvalRepo = app(ApprovalSnapshotRepository::class);
        $masterFlow = $approvalRepo->findMasterApprovalFlowByCompany(
            moduleType: 'user_transition',
            companyId: Auth::user()->company_id
        );

        if (!$masterFlow) {
            \Filament\Notifications\Notification::make()
                ->title('Approval Flow Not Found')
                ->body('No active approval flow found for user transitions for this company.')
                ->danger()
                ->send();

            throw new \Exception('No active approval flow found for user transitions for this company.');
        }

        foreach ($this->attachmentData as $file) {
            $this->record->attachments()->create([
                'file_path' => $file,
            ]);
        }

        $approvalService = app(ApprovalCreationService::class);

        $approvalService->createSnapshot(
            moduleType: 'user_transition',
            moduleId: $this->record->id,
            flow: $masterFlow,
            createdBy: Auth::id(),
            companyId: auth()->user()->company_id,
            userId: Auth::id(),
        );
    }
}
