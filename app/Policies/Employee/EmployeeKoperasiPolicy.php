<?php

declare(strict_types=1);

namespace App\Policies\Employee;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Employee\EmployeeKoperasi;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeKoperasiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeKoperasi');
    }

    public function view(AuthUser $authUser, EmployeeKoperasi $employeeKoperasi): bool
    {
        return $authUser->can('View:EmployeeKoperasi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeKoperasi');
    }

    public function update(AuthUser $authUser, ?EmployeeKoperasi $employeeKoperasi = null): bool
    {
        return $authUser->can('Update:EmployeeKoperasi');
    }

    public function delete(AuthUser $authUser, ?EmployeeKoperasi $employeeKoperasi = null): bool
    {
        return $authUser->can('Delete:EmployeeKoperasi');
    }

    public function restore(AuthUser $authUser, EmployeeKoperasi $employeeKoperasi): bool
    {
        return $authUser->can('Restore:EmployeeKoperasi');
    }

    public function forceDelete(AuthUser $authUser, EmployeeKoperasi $employeeKoperasi): bool
    {
        return $authUser->can('ForceDelete:EmployeeKoperasi');
    }

}