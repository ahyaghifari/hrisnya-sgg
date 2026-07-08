<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

class PermissionPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, Permission $permission): bool
    {
        return $authUser->can('View:Permission');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Permission');
    }

    public function update(AuthUser $authUser, Permission $permission): bool
    {
        return $authUser->can('Update:Permission');
    }

    public function delete(AuthUser $authUser, Permission $permission): bool
    {
        return $authUser->can('Delete:Permission');
    }

}