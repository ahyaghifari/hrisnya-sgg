<?php

declare(strict_types=1);

namespace App\Policies\Assessment;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Assessment\AssessmentUsers;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentUsersPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssessmentUsers');
    }

    public function view(AuthUser $authUser, AssessmentUsers $assessmentUsers): bool
    {
        return $authUser->can('View:AssessmentUsers');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssessmentUsers');
    }

    public function update(AuthUser $authUser, AssessmentUsers $assessmentUsers): bool
    {
        return $authUser->can('Update:AssessmentUsers');
    }

    public function delete(AuthUser $authUser, AssessmentUsers $assessmentUsers): bool
    {
        return $authUser->can('Delete:AssessmentUsers');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssessmentUsers');
    }

    public function restore(AuthUser $authUser, AssessmentUsers $assessmentUsers): bool
    {
        return $authUser->can('Restore:AssessmentUsers');
    }

    public function forceDelete(AuthUser $authUser, AssessmentUsers $assessmentUsers): bool
    {
        return $authUser->can('ForceDelete:AssessmentUsers');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssessmentUsers');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssessmentUsers');
    }

    public function replicate(AuthUser $authUser, AssessmentUsers $assessmentUsers): bool
    {
        return $authUser->can('Replicate:AssessmentUsers');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssessmentUsers');
    }

}