<?php

declare(strict_types=1);

namespace App\Policies\Master\Attendance;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Attendance\LeaveTypes;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveTypesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeaveTypes');
    }

    public function view(AuthUser $authUser, LeaveTypes $leaveTypes): bool
    {
        return $authUser->can('View:LeaveTypes');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeaveTypes');
    }

    public function update(AuthUser $authUser, LeaveTypes $leaveTypes): bool
    {
        return $authUser->can('Update:LeaveTypes');
    }

    public function delete(AuthUser $authUser, LeaveTypes $leaveTypes): bool
    {
        return $authUser->can('Delete:LeaveTypes');
    }

    public function restore(AuthUser $authUser, LeaveTypes $leaveTypes): bool
    {
        return $authUser->can('Restore:LeaveTypes');
    }

    public function forceDelete(AuthUser $authUser, LeaveTypes $leaveTypes): bool
    {
        return $authUser->can('ForceDelete:LeaveTypes');
    }

}