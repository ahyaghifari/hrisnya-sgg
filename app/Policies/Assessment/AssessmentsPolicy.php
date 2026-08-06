<?php

declare(strict_types=1);

namespace App\Policies\Assessment;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Assessment\Assessments;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Assessments');
    }

    public function view(AuthUser $authUser, Assessments $assessments): bool
    {
        return $authUser->can('View:Assessments');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Assessments');
    }

    public function update(AuthUser $authUser, Assessments $assessments): bool
    {
        return $authUser->can('Update:Assessments');
    }

    public function delete(AuthUser $authUser, Assessments $assessments): bool
    {
        return $authUser->can('Delete:Assessments');
    }

    public function restore(AuthUser $authUser, Assessments $assessments): bool
    {
        return $authUser->can('Restore:Assessments');
    }

}