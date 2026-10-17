<?php

declare(strict_types=1);

namespace App\Policies\Employee;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Employee\EmployeeExecutiveAllowance;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeExecutiveAllowancePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeExecutiveAllowance');
    }

    public function view(AuthUser $authUser, EmployeeExecutiveAllowance $employeeExecutiveAllowance): bool
    {
        return $authUser->can('View:EmployeeExecutiveAllowance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeExecutiveAllowance');
    }

    public function update(AuthUser $authUser, EmployeeExecutiveAllowance $employeeExecutiveAllowance): bool
    {
        return $authUser->can('Update:EmployeeExecutiveAllowance');
    }

    public function delete(AuthUser $authUser, EmployeeExecutiveAllowance $employeeExecutiveAllowance): bool
    {
        return $authUser->can('Delete:EmployeeExecutiveAllowance');
    }

    public function restore(AuthUser $authUser, EmployeeExecutiveAllowance $employeeExecutiveAllowance): bool
    {
        return $authUser->can('Restore:EmployeeExecutiveAllowance');
    }

    public function forceDelete(AuthUser $authUser, EmployeeExecutiveAllowance $employeeExecutiveAllowance): bool
    {
        return $authUser->can('ForceDelete:EmployeeExecutiveAllowance');
    }

}