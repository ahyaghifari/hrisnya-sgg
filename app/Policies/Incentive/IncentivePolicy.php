<?php

declare(strict_types=1);

namespace App\Policies\Incentive;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Incentive\Incentive;
use Illuminate\Auth\Access\HandlesAuthorization;

class IncentivePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Incentive');
    }

    public function view(AuthUser $authUser, Incentive $incentive): bool
    {
        return $authUser->can('View:Incentive');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Incentive');
    }

    public function update(AuthUser $authUser, Incentive $incentive): bool
    {
        return $authUser->can('Update:Incentive');
    }

    public function delete(AuthUser $authUser, Incentive $incentive): bool
    {
        return $authUser->can('Delete:Incentive');
    }

    public function restore(AuthUser $authUser, Incentive $incentive): bool
    {
        return $authUser->can('Restore:Incentive');
    }

    public function forceDelete(AuthUser $authUser, Incentive $incentive): bool
    {
        return $authUser->can('ForceDelete:Incentive');
    }

    public function restoreAny(AuthUser $authUser, Incentive $incentive): bool
    {
        return $authUser->can('RestoreAny:Incentive');
    }

}