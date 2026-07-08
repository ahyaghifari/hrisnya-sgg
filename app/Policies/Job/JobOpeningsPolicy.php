<?php

declare(strict_types=1);

namespace App\Policies\Job;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Job\JobOpenings;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobOpeningsPolicy
{
    use HandlesAuthorization;
    
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

}