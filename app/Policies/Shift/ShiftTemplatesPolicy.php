<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ShiftTemplates;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftTemplatesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShiftTemplates');
    }

    public function view(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('View:ShiftTemplates');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShiftTemplates');
    }

    public function update(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('Update:ShiftTemplates');
    }

    public function delete(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('Delete:ShiftTemplates');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ShiftTemplates');
    }

    public function restore(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('Restore:ShiftTemplates');
    }

    public function forceDelete(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('ForceDelete:ShiftTemplates');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ShiftTemplates');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ShiftTemplates');
    }

    public function replicate(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('Replicate:ShiftTemplates');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ShiftTemplates');
    }

}