<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\TrainerAssesmentsResults;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainerAssesmentsResultsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TrainerAssesmentsResults');
    }

    public function view(AuthUser $authUser, TrainerAssesmentsResults $trainerAssesmentsResults): bool
    {
        return $authUser->can('View:TrainerAssesmentsResults');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TrainerAssesmentsResults');
    }

    public function update(AuthUser $authUser, TrainerAssesmentsResults $trainerAssesmentsResults): bool
    {
        return $authUser->can('Update:TrainerAssesmentsResults');
    }

    public function delete(AuthUser $authUser, TrainerAssesmentsResults $trainerAssesmentsResults): bool
    {
        return $authUser->can('Delete:TrainerAssesmentsResults');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TrainerAssesmentsResults');
    }

    public function restore(AuthUser $authUser, TrainerAssesmentsResults $trainerAssesmentsResults): bool
    {
        return $authUser->can('Restore:TrainerAssesmentsResults');
    }

    public function forceDelete(AuthUser $authUser, TrainerAssesmentsResults $trainerAssesmentsResults): bool
    {
        return $authUser->can('ForceDelete:TrainerAssesmentsResults');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TrainerAssesmentsResults');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TrainerAssesmentsResults');
    }

    public function replicate(AuthUser $authUser, TrainerAssesmentsResults $trainerAssesmentsResults): bool
    {
        return $authUser->can('Replicate:TrainerAssesmentsResults');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TrainerAssesmentsResults');
    }

}