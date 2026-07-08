<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\EmployeeTypes;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeTypesPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, EmployeeTypes $employeeTypes): bool
    {
        return $authUser->can('View:EmployeeTypes');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeTypes');
    }

    public function update(AuthUser $authUser, EmployeeTypes $employeeTypes): bool
    {
        return $authUser->can('Update:EmployeeTypes');
    }

    public function delete(AuthUser $authUser, EmployeeTypes $employeeTypes): bool
    {
        return $authUser->can('Delete:EmployeeTypes');
    }

}