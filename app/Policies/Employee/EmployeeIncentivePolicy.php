<?php

declare(strict_types=1);

namespace App\Policies\Employee;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Employee\EmployeeIncentive;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeIncentivePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeIncentive');
    }

    public function view(AuthUser $authUser, EmployeeIncentive $employeeIncentive): bool
    {
        return $authUser->can('View:EmployeeIncentive');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeIncentive');
    }

    public function update(AuthUser $authUser, EmployeeIncentive $employeeIncentive): bool
    {
        return $authUser->can('Update:EmployeeIncentive');
    }

    public function delete(AuthUser $authUser, EmployeeIncentive $employeeIncentive): bool
    {
        return $authUser->can('Delete:EmployeeIncentive');
    }

    public function restore(AuthUser $authUser, EmployeeIncentive $employeeIncentive): bool
    {
        return $authUser->can('Restore:EmployeeIncentive');
    }

    public function forceDelete(AuthUser $authUser, EmployeeIncentive $employeeIncentive): bool
    {
        return $authUser->can('ForceDelete:EmployeeIncentive');
    }

}