<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Role;
use App\Models\User\UserJobPositions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;
    protected static ?string $title = "Edit Karyawan";

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Hapus')
                ->visible(fn ($record) => EmployeeResource::canDelete($record)),
        ];
    }

    protected function afterSave(): void
    {
        $roleIds = $this->data['role_ids'] ?? [];
        $this->record->syncRoles(Role::whereIn('id', $roleIds)->get());

        // Get the job_position_id from profile
        $jobPositionId = $this->record->profile?->job_position_id;

        if ($jobPositionId) {
            // Check if there's an active job position
            $activeJobPosition = $this->record->activeJobPosition;

            // If the job position has changed or there's no active job position
            if (!$activeJobPosition || $activeJobPosition->job_position_id != $jobPositionId) {
                // Delete all existing job position records to prevent duplicates
                $this->record->jobPositions()->delete();

                // Create new active job position
                UserJobPositions::create([
                    'user_id' => $this->record->id,
                    'job_position_id' => $jobPositionId,
                    'assigned_by' => Auth::id(),
                    'start_date' => now(),
                    'status' => 'active',
                ]);
            }
        }
    }
}
