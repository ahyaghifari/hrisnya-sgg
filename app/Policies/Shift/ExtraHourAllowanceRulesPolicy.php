<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ExtraHourAllowanceRules;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExtraHourAllowanceRulesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExtraHourAllowanceRules');
    }

    public function view(AuthUser $authUser, ExtraHourAllowanceRules $extraHourAllowanceRules): bool
    {
        return $authUser->can('View:ExtraHourAllowanceRules');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExtraHourAllowanceRules');
    }

    public function update(AuthUser $authUser, ExtraHourAllowanceRules $extraHourAllowanceRules): bool
    {
        return $authUser->can('Update:ExtraHourAllowanceRules');
    }

    public function delete(AuthUser $authUser, ExtraHourAllowanceRules $extraHourAllowanceRules): bool
    {
        return $authUser->can('Delete:ExtraHourAllowanceRules');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExtraHourAllowanceRules');
    }

    public function restore(AuthUser $authUser, ExtraHourAllowanceRules $extraHourAllowanceRules): bool
    {
        return $authUser->can('Restore:ExtraHourAllowanceRules');
    }

    public function forceDelete(AuthUser $authUser, ExtraHourAllowanceRules $extraHourAllowanceRules): bool
    {
        return $authUser->can('ForceDelete:ExtraHourAllowanceRules');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExtraHourAllowanceRules');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExtraHourAllowanceRules');
    }

    public function replicate(AuthUser $authUser, ExtraHourAllowanceRules $extraHourAllowanceRules): bool
    {
        return $authUser->can('Replicate:ExtraHourAllowanceRules');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExtraHourAllowanceRules');
    }

}