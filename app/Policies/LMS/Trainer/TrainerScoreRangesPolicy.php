<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\TrainerScoreRanges;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainerScoreRangesPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, TrainerScoreRanges $trainerScoreRanges): bool
    {
        return $authUser->can('View:TrainerScoreRanges');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TrainerScoreRanges');
    }

    public function update(AuthUser $authUser, TrainerScoreRanges $trainerScoreRanges): bool
    {
        return $authUser->can('Update:TrainerScoreRanges');
    }

    public function delete(AuthUser $authUser, TrainerScoreRanges $trainerScoreRanges): bool
    {
        return $authUser->can('Delete:TrainerScoreRanges');
    }

}