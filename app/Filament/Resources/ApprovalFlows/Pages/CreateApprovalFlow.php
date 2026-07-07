<?php

namespace App\Filament\Resources\ApprovalFlows\Pages;

use App\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateApprovalFlow extends CreateRecord
{
    protected static string $resource = ApprovalFlowResource::class;
    protected static ?string $title = 'Buat Alur Persetujuan Baru';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove steps dan assignments dari data utama karena akan dihandle manual
        unset($data['steps']);
        unset($data['assignment_job_positions']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $steps = $this->data['steps'] ?? [];

        // Simpan assignments jabatan — hanya jika flow_type adalah 'specific'
        if (($this->data['flow_type'] ?? null) === 'specific') {
            $jobPositionIds = $this->data['assignment_job_positions'] ?? [];
            foreach ($jobPositionIds as $jobPositionId) {
                $record->assignments()->create([
                    'assignable_id' => $jobPositionId,
                    'assignable_type' => \App\Models\Master\JobPositions::class,
                ]);
            }
        }

        foreach ($steps as $step) {
            // Pastikan semua field required ada nilai dan tidak kosong
            if (empty($step['approver_ref_id']) || empty($step['approver_type'])) {
                continue; // Skip step yang tidak valid
            }

            $stepData = [
                'flow_id' => $record->id,
                'step_order' => $step['step_order'] ?? 1,
                'approver_type' => $step['approver_type'],
                'approver_ref_id' => $step['approver_ref_id'],
                'approval_mode' => $step['approval_mode'] ?? 'any',
                'scope_type' => $step['scope_type'] ?? 'same_department',
                'scope_ref_id' => $step['scope_ref_id'] ?? null,
                'is_final' => $step['is_final'] ?? false,
            ];

            Log::info('Saving step data:', $stepData);
            $record->steps()->create($stepData);
        }
    }
}
