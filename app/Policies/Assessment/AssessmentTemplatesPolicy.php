<?php

declare(strict_types=1);

namespace App\Policies\Assessment;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Assessment\AssessmentTemplates;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentTemplatesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssessmentTemplates');
    }

    public function view(AuthUser $authUser, AssessmentTemplates $assessmentTemplates): bool
    {
        return $authUser->can('View:AssessmentTemplates');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssessmentTemplates');
    }

    public function update(AuthUser $authUser, AssessmentTemplates $assessmentTemplates): bool
    {
        return $authUser->can('Update:AssessmentTemplates');
    }

    public function delete(AuthUser $authUser, AssessmentTemplates $assessmentTemplates): bool
    {
        return $authUser->can('Delete:AssessmentTemplates');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssessmentTemplates');
    }

    public function restore(AuthUser $authUser, AssessmentTemplates $assessmentTemplates): bool
    {
        return $authUser->can('Restore:AssessmentTemplates');
    }

    public function forceDelete(AuthUser $authUser, AssessmentTemplates $assessmentTemplates): bool
    {
        return $authUser->can('ForceDelete:AssessmentTemplates');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssessmentTemplates');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssessmentTemplates');
    }

    public function replicate(AuthUser $authUser, AssessmentTemplates $assessmentTemplates): bool
    {
        return $authUser->can('Replicate:AssessmentTemplates');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssessmentTemplates');
    }

}