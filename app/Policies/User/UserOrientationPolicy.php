<?php

declare(strict_types=1);

namespace App\Policies\User;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\User\UserOrientation;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserOrientationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserOrientation');
    }

    public function view(AuthUser $authUser, UserOrientation $userOrientation): bool
    {
        return $authUser->can('View:UserOrientation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserOrientation');
    }

    public function update(AuthUser $authUser, UserOrientation $userOrientation): bool
    {
        return $authUser->can('Update:UserOrientation');
    }

    public function delete(AuthUser $authUser, UserOrientation $userOrientation): bool
    {
        return $authUser->can('Delete:UserOrientation');
    }

    public function restore(AuthUser $authUser, UserOrientation $userOrientation): bool
    {
        return $authUser->can('Restore:UserOrientation');
    }

    public function forceDelete(AuthUser $authUser, UserOrientation $userOrientation): bool
    {
        return $authUser->can('ForceDelete:UserOrientation');
    }

}