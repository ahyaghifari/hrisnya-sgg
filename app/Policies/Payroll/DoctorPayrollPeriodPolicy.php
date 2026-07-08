<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\DoctorPayrollPeriod;
use Illuminate\Auth\Access\HandlesAuthorization;

class DoctorPayrollPeriodPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, DoctorPayrollPeriod $doctorPayrollPeriod): bool
    {
        return $authUser->can('View:DoctorPayrollPeriod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DoctorPayrollPeriod');
    }

    public function update(AuthUser $authUser, DoctorPayrollPeriod $doctorPayrollPeriod): bool
    {
        return $authUser->can('Update:DoctorPayrollPeriod');
    }

    public function delete(AuthUser $authUser, DoctorPayrollPeriod $doctorPayrollPeriod): bool
    {
        return $authUser->can('Delete:DoctorPayrollPeriod');
    }

}