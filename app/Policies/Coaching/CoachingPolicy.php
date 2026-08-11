<?php

declare(strict_types=1);

namespace App\Policies\Coaching;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Coaching\Coaching;
use Illuminate\Auth\Access\HandlesAuthorization;

class CoachingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Coaching');
    }

    public function view(AuthUser $authUser, Coaching $coaching): bool
    {
        return $authUser->can('View:Coaching');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Coaching');
    }

    public function update(AuthUser $authUser, Coaching $coaching): bool
    {
        return $authUser->can('Update:Coaching');
    }

    public function delete(AuthUser $authUser, Coaching $coaching): bool
    {
        return $authUser->can('Delete:Coaching');
    }

    public function restore(AuthUser $authUser, Coaching $coaching): bool
    {
        return $authUser->can('Restore:Coaching');
    }

    public function forceDelete(AuthUser $authUser, Coaching $coaching): bool
    {
        return $authUser->can('ForceDelete:Coaching');
    }

}