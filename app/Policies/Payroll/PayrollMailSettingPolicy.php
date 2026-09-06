<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\PayrollMailSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollMailSettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PayrollMailSetting');
    }

    public function view(AuthUser $authUser, PayrollMailSetting $payrollMailSetting): bool
    {
        return $authUser->can('View:PayrollMailSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PayrollMailSetting');
    }

    public function update(AuthUser $authUser, PayrollMailSetting $payrollMailSetting): bool
    {
        return $authUser->can('Update:PayrollMailSetting');
    }

    public function delete(AuthUser $authUser, PayrollMailSetting $payrollMailSetting): bool
    {
        return $authUser->can('Delete:PayrollMailSetting');
    }

    public function restore(AuthUser $authUser, PayrollMailSetting $payrollMailSetting): bool
    {
        return $authUser->can('Restore:PayrollMailSetting');
    }

    public function forceDelete(AuthUser $authUser, PayrollMailSetting $payrollMailSetting): bool
    {
        return $authUser->can('ForceDelete:PayrollMailSetting');
    }

}