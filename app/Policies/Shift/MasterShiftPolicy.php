<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\MasterShift;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterShiftPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MasterShift');
    }

    public function view(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('View:MasterShift');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MasterShift');
    }

    public function update(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('Update:MasterShift');
    }

    public function delete(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('Delete:MasterShift');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MasterShift');
    }

    public function restore(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('Restore:MasterShift');
    }

    public function forceDelete(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('ForceDelete:MasterShift');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MasterShift');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MasterShift');
    }

    public function replicate(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('Replicate:MasterShift');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MasterShift');
    }

}