<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ShiftScheduleAssignments;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftScheduleAssignmentsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShiftScheduleAssignments');
    }

    public function view(AuthUser $authUser, ShiftScheduleAssignments $shiftScheduleAssignments): bool
    {
        return $authUser->can('View:ShiftScheduleAssignments');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShiftScheduleAssignments');
    }

    public function update(AuthUser $authUser, ShiftScheduleAssignments $shiftScheduleAssignments): bool
    {
        return $authUser->can('Update:ShiftScheduleAssignments');
    }

    public function delete(AuthUser $authUser, ShiftScheduleAssignments $shiftScheduleAssignments): bool
    {
        return $authUser->can('Delete:ShiftScheduleAssignments');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ShiftScheduleAssignments');
    }

    public function restore(AuthUser $authUser, ShiftScheduleAssignments $shiftScheduleAssignments): bool
    {
        return $authUser->can('Restore:ShiftScheduleAssignments');
    }

    public function forceDelete(AuthUser $authUser, ShiftScheduleAssignments $shiftScheduleAssignments): bool
    {
        return $authUser->can('ForceDelete:ShiftScheduleAssignments');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ShiftScheduleAssignments');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ShiftScheduleAssignments');
    }

    public function replicate(AuthUser $authUser, ShiftScheduleAssignments $shiftScheduleAssignments): bool
    {
        return $authUser->can('Replicate:ShiftScheduleAssignments');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ShiftScheduleAssignments');
    }

}