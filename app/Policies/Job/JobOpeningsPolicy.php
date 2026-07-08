<?php

declare(strict_types=1);

namespace App\Policies\Job;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Job\JobOpenings;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobOpeningsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JobOpenings');
    }

    public function view(AuthUser $authUser, JobOpenings $jobOpenings): bool
    {
        return $authUser->can('View:JobOpenings');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JobOpenings');
    }

    public function update(AuthUser $authUser, JobOpenings $jobOpenings): bool
    {
        return $authUser->can('Update:JobOpenings');
    }

    public function delete(AuthUser $authUser, JobOpenings $jobOpenings): bool
    {
        return $authUser->can('Delete:JobOpenings');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JobOpenings');
    }

    public function restore(AuthUser $authUser, JobOpenings $jobOpenings): bool
    {
        return $authUser->can('Restore:JobOpenings');
    }

    public function forceDelete(AuthUser $authUser, JobOpenings $jobOpenings): bool
    {
        return $authUser->can('ForceDelete:JobOpenings');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JobOpenings');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JobOpenings');
    }

    public function replicate(AuthUser $authUser, JobOpenings $jobOpenings): bool
    {
        return $authUser->can('Replicate:JobOpenings');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JobOpenings');
    }

}