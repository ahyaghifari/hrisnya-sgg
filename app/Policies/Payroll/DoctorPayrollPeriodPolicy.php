<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\DoctorPayrollPeriod;
use Illuminate\Auth\Access\HandlesAuthorization;

class DoctorPayrollPeriodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DoctorPayrollPeriod');
    }

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

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DoctorPayrollPeriod');
    }

    public function restore(AuthUser $authUser, DoctorPayrollPeriod $doctorPayrollPeriod): bool
    {
        return $authUser->can('Restore:DoctorPayrollPeriod');
    }

    public function forceDelete(AuthUser $authUser, DoctorPayrollPeriod $doctorPayrollPeriod): bool
    {
        return $authUser->can('ForceDelete:DoctorPayrollPeriod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DoctorPayrollPeriod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DoctorPayrollPeriod');
    }

    public function replicate(AuthUser $authUser, DoctorPayrollPeriod $doctorPayrollPeriod): bool
    {
        return $authUser->can('Replicate:DoctorPayrollPeriod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DoctorPayrollPeriod');
    }

}