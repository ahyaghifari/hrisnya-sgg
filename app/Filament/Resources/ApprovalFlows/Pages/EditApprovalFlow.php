<?php

namespace App\Filament\Resources\ApprovalFlows\Pages;

use App\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EditApprovalFlow extends EditRecord
{
    protected static string $resource = ApprovalFlowResource::class;
    protected static ?string $title = 'Edit Alur Persetujuan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing steps data ke dalam form
        $data['steps'] = $this->record->steps->map(function ($step) {
            return [
                'step_order' => $step->step_order,
                'approver_type' => $step->approver_type,
                'approver_ref_id' => $step->approver_ref_id,
                'supervisor_level' => $step->supervisor_level,
                'approval_mode' => $step->approval_mode,
                'scope_type' => $step->scope_type,
                'scope_ref_id' => $step->scope_ref_id,
                'is_final' => $step->is_final,
            ];
        })->toArray();

        // Load existing assignments (jabatan)
        $data['assignment_job_positions'] = $this->record->assignments()
            ->where('assignable_type', \App\Models\Master\JobPositions::class)
            ->pluck('assignable_id')
            ->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove steps dan assignments dari data utama karena akan dihandle manual
        unset($data['steps']);
        unset($data['assignment_job_positions']);
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $record->update(
            [
                'updated_at' => now(),
                'updated_by' => Auth::id(),
            ]
        );
        $steps = $this->data['steps'] ?? [];

        // Sync assignments jabatan — hanya jika flow_type adalah 'specific'
        $record->assignments()->delete();
        if (($this->data['flow_type'] ?? null) === 'specific') {
            $jobPositionIds = $this->data['assignment_job_positions'] ?? [];
            foreach ($jobPositionIds as $jobPositionId) {
                $record->assignments()->create([
                    'assignable_id' => $jobPositionId,
                    'assignable_type' => \App\Models\Master\JobPositions::class,
                ]);
            }
        }

        // Hapus semua steps yang ada
        $record->steps()->delete();

        // Tambah ulang steps yang baru
        foreach ($steps as $step) {
            // Pastikan semua field required ada nilai dan tidak kosong. Step 'supervisor'
            // (atasan pengaju) sah tanpa approver_ref_id — di-resolve saat snapshot.
            $isSupervisor = ($step['approver_type'] ?? null) === 'supervisor';
            if (empty($step['approver_type']) || (empty($step['approver_ref_id']) && ! $isSupervisor)) {
                continue; // Skip step yang tidak valid
            }

            $stepData = [
                'flow_id' => $record->id,
                'step_order' => $step['step_order'] ?? 1,
                'approver_type' => $step['approver_type'],
                'approver_ref_id' => $isSupervisor ? null : ($step['approver_ref_id'] ?? null),
                'supervisor_level' => $isSupervisor ? (int) ($step['supervisor_level'] ?? 1) : null,
                'approval_mode' => $step['approval_mode'] ?? 'any',
                'scope_type' => $step['scope_type'] ?? 'same_department',
                'scope_ref_id' => $step['scope_ref_id'] ?? null,
                'is_final' => $step['is_final'] ?? false,
            ];

            Log::info('Updating step data:', $stepData);
            $record->steps()->create($stepData);
        }
    }
}
