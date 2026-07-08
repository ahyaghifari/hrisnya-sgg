<?php

declare(strict_types=1);

namespace App\Policies\User;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\User\UserTransition;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserTransitionPolicy
{
    use HandlesAuthorization;
    
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

}