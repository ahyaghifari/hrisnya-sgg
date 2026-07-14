<?php

declare(strict_types=1);

namespace App\Policies\User;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\User\UserTransition;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserTransitionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserTransition');
    }

    public function view(AuthUser $authUser, UserTransition $userTransition): bool
    {
        return $authUser->can('View:UserTransition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserTransition');
    }

    public function update(AuthUser $authUser, UserTransition $userTransition): bool
    {
        return $authUser->can('Update:UserTransition');
    }

    public function delete(AuthUser $authUser, UserTransition $userTransition): bool
    {
        return $authUser->can('Delete:UserTransition');
    }

    public function restore(AuthUser $authUser, UserTransition $userTransition): bool
    {
        return $authUser->can('Restore:UserTransition');
    }

    public function forceDelete(AuthUser $authUser, UserTransition $userTransition): bool
    {
        return $authUser->can('ForceDelete:UserTransition');
    }

    public function restoreAny(AuthUser $authUser, UserTransition $userTransition): bool
    {
        return $authUser->can('RestoreAny:UserTransition');
    }

    public function replicate(AuthUser $authUser, UserTransition $userTransition): bool
    {
        return $authUser->can('Replicate:UserTransition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserTransition');
    }

}