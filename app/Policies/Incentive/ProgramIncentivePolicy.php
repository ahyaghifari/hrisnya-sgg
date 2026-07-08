<?php

declare(strict_types=1);

namespace App\Policies\Incentive;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Incentive\ProgramIncentive;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProgramIncentivePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProgramIncentive');
    }

    public function view(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('View:ProgramIncentive');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProgramIncentive');
    }

    public function update(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('Update:ProgramIncentive');
    }

    public function delete(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('Delete:ProgramIncentive');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProgramIncentive');
    }

    public function restore(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('Restore:ProgramIncentive');
    }

    public function forceDelete(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('ForceDelete:ProgramIncentive');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProgramIncentive');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProgramIncentive');
    }

    public function replicate(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('Replicate:ProgramIncentive');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProgramIncentive');
    }

}