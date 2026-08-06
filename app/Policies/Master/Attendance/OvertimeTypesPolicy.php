<?php

declare(strict_types=1);

namespace App\Policies\Master\Attendance;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Attendance\OvertimeTypes;
use Illuminate\Auth\Access\HandlesAuthorization;

class OvertimeTypesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OvertimeTypes');
    }

    public function view(AuthUser $authUser, OvertimeTypes $overtimeTypes): bool
    {
        return $authUser->can('View:OvertimeTypes');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OvertimeTypes');
    }

    public function update(AuthUser $authUser, OvertimeTypes $overtimeTypes): bool
    {
        return $authUser->can('Update:OvertimeTypes');
    }

    public function delete(AuthUser $authUser, OvertimeTypes $overtimeTypes): bool
    {
        return $authUser->can('Delete:OvertimeTypes');
    }

    public function restore(AuthUser $authUser, OvertimeTypes $overtimeTypes): bool
    {
        return $authUser->can('Restore:OvertimeTypes');
    }

    public function forceDelete(AuthUser $authUser, OvertimeTypes $overtimeTypes): bool
    {
        return $authUser->can('ForceDelete:OvertimeTypes');
    }

    public function restoreAny(AuthUser $authUser, OvertimeTypes $overtimeTypes): bool
    {
        return $authUser->can('RestoreAny:OvertimeTypes');
    }

}