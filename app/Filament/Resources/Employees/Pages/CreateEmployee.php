<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Role;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
    protected static ?string $title = "Tambah Karyawan";

    protected function handleRecordCreation(array $data): Model
    {
        // Look for an existing user (including soft-deleted) with the same email or NIP
        $existing = User::withTrashed()
            ->where(function ($q) use ($data) {
                $q->where('email', $data['email']);
                if (!empty($data['nip'])) {
                    $q->orWhereIn('users.id', \App\Models\Employee\Employee::query()->select('user_id')->where('nip', $data['nip']));
                }
            })
            ->first();

        if ($existing) {
            $existing->restore();
            $fillable = (new User)->getFillable();
            $existing->update(array_intersect_key($data, array_flip($fillable)));

            // Update profile with new data
            if ($existing->profile) {
                $existing->profile->update(array_intersect_key(
                    $data['profile'] ?? [],
                    array_flip((new UserProfiles)->getFillable())
                ));
            }

            return $existing;
        }

        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        // role_ids bukan attribute User (dibuang mass-assignment di handleRecordCreation),
        // jadi assign role-nya manual di sini — bisa lebih dari 1 role sekaligus.
        $roleIds = $this->data['role_ids'] ?? [];
        $this->record->syncRoles(Role::whereIn('id', $roleIds)->get());

        // Get the job_position_id from profile
        $jobPositionId = $this->record->profile?->job_position_id;

        // Create UserJobPosition record if job_position_id exists
        if ($jobPositionId) {
            // Remove any existing records with a different job position to prevent duplicates
            $this->record->jobPositions()
                ->where('job_position_id', '!=', $jobPositionId)
                ->delete();

            // Create or update to prevent duplicate user_id + job_position_id
            UserJobPositions::updateOrCreate(
                [
                    'user_id' => $this->record->id,
                    'job_position_id' => $jobPositionId,
                ],
                [
                    'assigned_by' => Auth::id(),
                    'start_date' => $this->record->profile?->joining_date ?? now(),
                    'status' => 'active',
                ]
            );
        }
    }
}
