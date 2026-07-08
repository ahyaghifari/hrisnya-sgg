<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ShiftSwapRequests;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftSwapRequestsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShiftSwapRequests');
    }

    public function view(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('View:ShiftSwapRequests');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShiftSwapRequests');
    }

    public function update(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('Update:ShiftSwapRequests');
    }

    public function delete(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('Delete:ShiftSwapRequests');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ShiftSwapRequests');
    }

    public function restore(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('Restore:ShiftSwapRequests');
    }

    public function forceDelete(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('ForceDelete:ShiftSwapRequests');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ShiftSwapRequests');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ShiftSwapRequests');
    }

    public function replicate(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('Replicate:ShiftSwapRequests');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ShiftSwapRequests');
    }

}