<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\MasterSalaryComponents;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterSalaryComponentsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MasterSalaryComponents');
    }

    public function view(AuthUser $authUser, MasterSalaryComponents $masterSalaryComponents): bool
    {
        return $authUser->can('View:MasterSalaryComponents');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MasterSalaryComponents');
    }

    public function update(AuthUser $authUser, MasterSalaryComponents $masterSalaryComponents): bool
    {
        return $authUser->can('Update:MasterSalaryComponents');
    }

    public function delete(AuthUser $authUser, MasterSalaryComponents $masterSalaryComponents): bool
    {
        return $authUser->can('Delete:MasterSalaryComponents');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MasterSalaryComponents');
    }

    public function restore(AuthUser $authUser, MasterSalaryComponents $masterSalaryComponents): bool
    {
        return $authUser->can('Restore:MasterSalaryComponents');
    }

    public function forceDelete(AuthUser $authUser, MasterSalaryComponents $masterSalaryComponents): bool
    {
        return $authUser->can('ForceDelete:MasterSalaryComponents');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MasterSalaryComponents');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MasterSalaryComponents');
    }

    public function replicate(AuthUser $authUser, MasterSalaryComponents $masterSalaryComponents): bool
    {
        return $authUser->can('Replicate:MasterSalaryComponents');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MasterSalaryComponents');
    }

}