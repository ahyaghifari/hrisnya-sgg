<?php

declare(strict_types=1);

namespace App\Policies\User;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\User\UserContract;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserContractPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserContract');
    }

    public function view(AuthUser $authUser, UserContract $userContract): bool
    {
        return $authUser->can('View:UserContract');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserContract');
    }

    public function update(AuthUser $authUser, UserContract $userContract): bool
    {
        return $authUser->can('Update:UserContract');
    }

    public function delete(AuthUser $authUser, UserContract $userContract): bool
    {
        return $authUser->can('Delete:UserContract');
    }

    public function restore(AuthUser $authUser, UserContract $userContract): bool
    {
        return $authUser->can('Restore:UserContract');
    }

    public function forceDelete(AuthUser $authUser, UserContract $userContract): bool
    {
        return $authUser->can('ForceDelete:UserContract');
    }

}