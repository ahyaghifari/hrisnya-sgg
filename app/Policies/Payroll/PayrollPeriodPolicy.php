<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\PayrollPeriod;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPeriodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PayrollPeriod');
    }

    public function view(AuthUser $authUser, PayrollPeriod $payrollPeriod): bool
    {
        return $authUser->can('View:PayrollPeriod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PayrollPeriod');
    }

    public function update(AuthUser $authUser, PayrollPeriod $payrollPeriod): bool
    {
        return $authUser->can('Update:PayrollPeriod');
    }

    public function delete(AuthUser $authUser, PayrollPeriod $payrollPeriod): bool
    {
        return $authUser->can('Delete:PayrollPeriod');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PayrollPeriod');
    }

    public function restore(AuthUser $authUser, PayrollPeriod $payrollPeriod): bool
    {
        return $authUser->can('Restore:PayrollPeriod');
    }

    public function forceDelete(AuthUser $authUser, PayrollPeriod $payrollPeriod): bool
    {
        return $authUser->can('ForceDelete:PayrollPeriod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PayrollPeriod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PayrollPeriod');
    }

    public function replicate(AuthUser $authUser, PayrollPeriod $payrollPeriod): bool
    {
        return $authUser->can('Replicate:PayrollPeriod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PayrollPeriod');
    }

}