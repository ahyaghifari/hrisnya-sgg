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

    public function restore(AuthUser $authUser, TrainerAssessmentResults $trainerAssessmentResults): bool
    {
        return $authUser->can('Restore:TrainerAssessmentResults');
    }

}