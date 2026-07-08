<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\EmployeeTypes;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeTypesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeTypes');
    }

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

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmployeeTypes');
    }

    public function restore(AuthUser $authUser, EmployeeTypes $employeeTypes): bool
    {
        return $authUser->can('Restore:EmployeeTypes');
    }

    public function forceDelete(AuthUser $authUser, EmployeeTypes $employeeTypes): bool
    {
        return $authUser->can('ForceDelete:EmployeeTypes');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EmployeeTypes');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EmployeeTypes');
    }

    public function replicate(AuthUser $authUser, EmployeeTypes $employeeTypes): bool
    {
        return $authUser->can('Replicate:EmployeeTypes');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EmployeeTypes');
    }

}