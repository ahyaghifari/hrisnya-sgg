<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\TrainerAssessmentResults;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainerAssessmentResultsPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TrainerAssessmentResults');
    }

    public function view(AuthUser $authUser, TrainerAssessmentResults $trainerAssessmentResults): bool
    {
        return $authUser->can('View:TrainerAssessmentResults');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TrainerAssessmentResults');
    }

    public function update(AuthUser $authUser, TrainerAssessmentResults $trainerAssessmentResults): bool
    {
        return $authUser->can('Update:TrainerAssessmentResults');
    }

    public function delete(AuthUser $authUser, TrainerAssessmentResults $trainerAssessmentResults): bool
    {
        return $authUser->can('Delete:TrainerAssessmentResults');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TrainerAssessmentResults');
    }

    public function restore(AuthUser $authUser, TrainerAssessmentResults $trainerAssessmentResults): bool
    {
        return $authUser->can('Restore:TrainerAssessmentResults');
    }

    public function forceDelete(AuthUser $authUser, TrainerAssessmentResults $trainerAssessmentResults): bool
    {
        return $authUser->can('ForceDelete:TrainerAssessmentResults');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TrainerAssessmentResults');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TrainerAssessmentResults');
    }

    public function replicate(AuthUser $authUser, TrainerAssessmentResults $trainerAssessmentResults): bool
    {
        return $authUser->can('Replicate:TrainerAssessmentResults');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TrainerAssessmentResults');
    }

}
