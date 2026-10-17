<?php

declare(strict_types=1);

namespace App\Policies\Employee;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Employee\EmployeeBpjsDependent;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeBpjsDependentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeBpjsDependent');
    }

    public function view(AuthUser $authUser, EmployeeBpjsDependent $employeeBpjsDependent): bool
    {
        return $authUser->can('View:EmployeeBpjsDependent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeBpjsDependent');
    }

    public function update(AuthUser $authUser, EmployeeBpjsDependent $employeeBpjsDependent): bool
    {
        return $authUser->can('Update:EmployeeBpjsDependent');
    }

    public function delete(AuthUser $authUser, EmployeeBpjsDependent $employeeBpjsDependent): bool
    {
        return $authUser->can('Delete:EmployeeBpjsDependent');
    }

    public function restore(AuthUser $authUser, EmployeeBpjsDependent $employeeBpjsDependent): bool
    {
        return $authUser->can('Restore:EmployeeBpjsDependent');
    }

    public function forceDelete(AuthUser $authUser, EmployeeBpjsDependent $employeeBpjsDependent): bool
    {
        return $authUser->can('ForceDelete:EmployeeBpjsDependent');
    }

}