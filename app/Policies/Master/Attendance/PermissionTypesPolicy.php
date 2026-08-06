<?php

declare(strict_types=1);

namespace App\Policies\Master\Attendance;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Attendance\PermissionTypes;
use Illuminate\Auth\Access\HandlesAuthorization;

class PermissionTypesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PermissionTypes');
    }

    public function view(AuthUser $authUser, PermissionTypes $permissionTypes): bool
    {
        return $authUser->can('View:PermissionTypes');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PermissionTypes');
    }

    public function update(AuthUser $authUser, PermissionTypes $permissionTypes): bool
    {
        return $authUser->can('Update:PermissionTypes');
    }

    public function delete(AuthUser $authUser, PermissionTypes $permissionTypes): bool
    {
        return $authUser->can('Delete:PermissionTypes');
    }

    public function restore(AuthUser $authUser, PermissionTypes $permissionTypes): bool
    {
        return $authUser->can('Restore:PermissionTypes');
    }

    public function forceDelete(AuthUser $authUser, PermissionTypes $permissionTypes): bool
    {
        return $authUser->can('ForceDelete:PermissionTypes');
    }

    public function restoreAny(AuthUser $authUser, PermissionTypes $permissionTypes): bool
    {
        return $authUser->can('RestoreAny:PermissionTypes');
    }

}