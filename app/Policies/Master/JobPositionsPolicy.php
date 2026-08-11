<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\JobPositions;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobPositionsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JobPositions');
    }

    public function view(AuthUser $authUser, JobPositions $jobPositions): bool
    {
        return $authUser->can('View:JobPositions');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JobPositions');
    }

    public function update(AuthUser $authUser, JobPositions $jobPositions): bool
    {
        return $authUser->can('Update:JobPositions');
    }

    public function delete(AuthUser $authUser, JobPositions $jobPositions): bool
    {
        return $authUser->can('Delete:JobPositions');
    }

    public function restore(AuthUser $authUser, JobPositions $jobPositions): bool
    {
        return $authUser->can('Restore:JobPositions');
    }

    public function forceDelete(AuthUser $authUser, JobPositions $jobPositions): bool
    {
        return $authUser->can('ForceDelete:JobPositions');
    }

}