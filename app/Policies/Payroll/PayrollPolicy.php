<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\Payroll;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, Payroll $payroll): bool
    {
        return $authUser->can('View:Payroll');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Payroll');
    }

    public function update(AuthUser $authUser, Payroll $payroll): bool
    {
        return $authUser->can('Update:Payroll');
    }

    public function delete(AuthUser $authUser, Payroll $payroll): bool
    {
        return $authUser->can('Delete:Payroll');
    }

}