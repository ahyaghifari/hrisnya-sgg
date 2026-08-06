<?php

declare(strict_types=1);

namespace App\Policies\Assessment;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Assessment\AssessmentTypes;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentTypesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssessmentTypes');
    }

    public function view(AuthUser $authUser, AssessmentTypes $assessmentTypes): bool
    {
        return $authUser->can('View:AssessmentTypes');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssessmentTypes');
    }

    public function update(AuthUser $authUser, AssessmentTypes $assessmentTypes): bool
    {
        return $authUser->can('Update:AssessmentTypes');
    }

    public function delete(AuthUser $authUser, AssessmentTypes $assessmentTypes): bool
    {
        return $authUser->can('Delete:AssessmentTypes');
    }

    public function restore(AuthUser $authUser, AssessmentTypes $assessmentTypes): bool
    {
        return $authUser->can('Restore:AssessmentTypes');
    }

    public function forceDelete(AuthUser $authUser, AssessmentTypes $assessmentTypes): bool
    {
        return $authUser->can('ForceDelete:AssessmentTypes');
    }

    public function restoreAny(AuthUser $authUser, AssessmentTypes $assessmentTypes): bool
    {
        return $authUser->can('RestoreAny:AssessmentTypes');
    }

}