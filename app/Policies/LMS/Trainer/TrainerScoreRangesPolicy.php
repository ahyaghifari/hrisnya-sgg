<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\TrainerScoreRanges;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainerScoreRangesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TrainerScoreRanges');
    }

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

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TrainerScoreRanges');
    }

    public function restore(AuthUser $authUser, TrainerScoreRanges $trainerScoreRanges): bool
    {
        return $authUser->can('Restore:TrainerScoreRanges');
    }

    public function forceDelete(AuthUser $authUser, TrainerScoreRanges $trainerScoreRanges): bool
    {
        return $authUser->can('ForceDelete:TrainerScoreRanges');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TrainerScoreRanges');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TrainerScoreRanges');
    }

    public function replicate(AuthUser $authUser, TrainerScoreRanges $trainerScoreRanges): bool
    {
        return $authUser->can('Replicate:TrainerScoreRanges');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TrainerScoreRanges');
    }

}