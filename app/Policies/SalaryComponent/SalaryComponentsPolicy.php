<?php

declare(strict_types=1);

namespace App\Policies\SalaryComponent;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SalaryComponent\SalaryComponents;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalaryComponentsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SalaryComponents');
    }

    public function view(AuthUser $authUser, SalaryComponents $salaryComponents): bool
    {
        return $authUser->can('View:SalaryComponents');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SalaryComponents');
    }

    public function update(AuthUser $authUser, SalaryComponents $salaryComponents): bool
    {
        return $authUser->can('Update:SalaryComponents');
    }

    public function delete(AuthUser $authUser, SalaryComponents $salaryComponents): bool
    {
        return $authUser->can('Delete:SalaryComponents');
    }

    public function restore(AuthUser $authUser, SalaryComponents $salaryComponents): bool
    {
        return $authUser->can('Restore:SalaryComponents');
    }

    public function forceDelete(AuthUser $authUser, SalaryComponents $salaryComponents): bool
    {
        return $authUser->can('ForceDelete:SalaryComponents');
    }

    public function restoreAny(AuthUser $authUser, SalaryComponents $salaryComponents): bool
    {
        return $authUser->can('RestoreAny:SalaryComponents');
    }

    public function replicate(AuthUser $authUser, SalaryComponents $salaryComponents): bool
    {
        return $authUser->can('Replicate:SalaryComponents');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SalaryComponents');
    }

}