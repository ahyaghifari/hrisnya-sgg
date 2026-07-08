<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\TrainerScoreVariables;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainerScoreVariablesPolicy
{
    use HandlesAuthorization;
    
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

}