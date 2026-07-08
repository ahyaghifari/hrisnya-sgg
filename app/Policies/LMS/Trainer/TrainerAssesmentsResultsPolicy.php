<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\TrainerAssesmentsResults;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainerAssesmentsResultsPolicy
{
    use HandlesAuthorization;
    
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

}