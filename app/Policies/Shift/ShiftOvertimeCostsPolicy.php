<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ShiftOvertimeCosts;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftOvertimeCostsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShiftOvertimeCosts');
    }

    public function view(AuthUser $authUser, ShiftOvertimeCosts $shiftOvertimeCosts): bool
    {
        return $authUser->can('View:ShiftOvertimeCosts');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShiftOvertimeCosts');
    }

    public function update(AuthUser $authUser, ShiftOvertimeCosts $shiftOvertimeCosts): bool
    {
        return $authUser->can('Update:ShiftOvertimeCosts');
    }

    public function delete(AuthUser $authUser, ShiftOvertimeCosts $shiftOvertimeCosts): bool
    {
        return $authUser->can('Delete:ShiftOvertimeCosts');
    }

    public function restore(AuthUser $authUser, ShiftOvertimeCosts $shiftOvertimeCosts): bool
    {
        return $authUser->can('Restore:ShiftOvertimeCosts');
    }

    public function forceDelete(AuthUser $authUser, ShiftOvertimeCosts $shiftOvertimeCosts): bool
    {
        return $authUser->can('ForceDelete:ShiftOvertimeCosts');
    }

    public function restoreAny(AuthUser $authUser, ShiftOvertimeCosts $shiftOvertimeCosts): bool
    {
        return $authUser->can('RestoreAny:ShiftOvertimeCosts');
    }

}