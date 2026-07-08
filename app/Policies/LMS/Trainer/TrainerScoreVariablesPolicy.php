<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\TrainerScoreVariables;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainerScoreVariablesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TrainerScoreVariables');
    }

    public function view(AuthUser $authUser, TrainerScoreVariables $trainerScoreVariables): bool
    {
        return $authUser->can('View:TrainerScoreVariables');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TrainerScoreVariables');
    }

    public function update(AuthUser $authUser, TrainerScoreVariables $trainerScoreVariables): bool
    {
        return $authUser->can('Update:TrainerScoreVariables');
    }

    public function delete(AuthUser $authUser, TrainerScoreVariables $trainerScoreVariables): bool
    {
        return $authUser->can('Delete:TrainerScoreVariables');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TrainerScoreVariables');
    }

    public function restore(AuthUser $authUser, TrainerScoreVariables $trainerScoreVariables): bool
    {
        return $authUser->can('Restore:TrainerScoreVariables');
    }

    public function forceDelete(AuthUser $authUser, TrainerScoreVariables $trainerScoreVariables): bool
    {
        return $authUser->can('ForceDelete:TrainerScoreVariables');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TrainerScoreVariables');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TrainerScoreVariables');
    }

    public function replicate(AuthUser $authUser, TrainerScoreVariables $trainerScoreVariables): bool
    {
        return $authUser->can('Replicate:TrainerScoreVariables');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TrainerScoreVariables');
    }

}