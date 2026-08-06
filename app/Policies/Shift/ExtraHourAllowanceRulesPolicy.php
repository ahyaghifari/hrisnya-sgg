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

    public function restore(AuthUser $authUser, ExtraHourAllowanceRules $extraHourAllowanceRules): bool
    {
        return $authUser->can('Restore:ExtraHourAllowanceRules');
    }

}