<?php

namespace App\Filament\Resources\ApprovalFlows\Pages;

use App\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use App\Models\Master\JobPositions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateApprovalFlow extends CreateRecord
{
    protected static string $resource = ApprovalFlowResource::class;
    protected static ?string $title = 'Buat Alur Persetujuan Baru';

    // Duplikat: ?replicate={id} mengisi form dengan salinan alur sumber (bukan simpan langsung -
    // validasi bentrok modul/jenis/jabatan di form harus tetap jalan). Sumber diambil lewat
    // getEloquentQuery (scope company) + policy replicate, jadi tidak bisa nyalin alur company lain.
    protected function fillForm(): void
    {
        $source = ($id = request()->query('replicate'))
            ? ApprovalFlowResource::getEloquentQuery()->find($id)
            : null;

        if (! $source || ! Auth::user()->can('replicate', $source)) {
            parent::fillForm();

            return;
        }

        $this->callHook('beforeFill');

        $this->form->fill([
            'company_id' => $source->company_id,
            'name' => $source->name.' (Salinan)',
            'module' => $source->module,
            'sub_module' => $source->sub_module,
            'flow_type' => $source->flow_type,
            'assignment_job_positions' => $source->assignments()
                ->where('assignable_type', JobPositions::class)
                ->pluck('assignable_id')
                ->toArray(),
            'steps' => $source->steps->map(fn ($step) => $step->only([
                'step_order', 'approver_type', 'approver_ref_id', 'supervisor_level',
                'approval_mode', 'scope_type', 'scope_ref_id', 'is_final',
            ]))->toArray(),
        ]);

        $this->callHook('afterFill');
    }

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

            Log::info('Saving step data:', $stepData);
            $record->steps()->create($stepData);
        }
    }
}
