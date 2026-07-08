<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\MasterSalaryComponents;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterSalaryComponentsPolicy
{
    use HandlesAuthorization;
    
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

}