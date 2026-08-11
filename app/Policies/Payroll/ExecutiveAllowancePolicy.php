<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use App\Models\Payroll\ExecutiveAllowance;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ExecutiveAllowancePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExecutiveAllowance');
    }

    public function view(AuthUser $authUser, ExecutiveAllowance $executiveAllowance): bool
    {
        return $authUser->can('View:ExecutiveAllowance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExecutiveAllowance');
    }

    public function update(AuthUser $authUser, ExecutiveAllowance $executiveAllowance): bool
    {
        return $authUser->can('Update:ExecutiveAllowance');
    }

    public function delete(AuthUser $authUser, ExecutiveAllowance $executiveAllowance): bool
    {
        return $authUser->can('Delete:ExecutiveAllowance');
    }

    public function restore(AuthUser $authUser, ExecutiveAllowance $executiveAllowance): bool
    {
        return $authUser->can('Restore:ExecutiveAllowance');
    }
}
