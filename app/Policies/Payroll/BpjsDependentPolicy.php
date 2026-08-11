<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\BpjsDependent;
use Illuminate\Auth\Access\HandlesAuthorization;

class BpjsDependentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BpjsDependent');
    }

    public function view(AuthUser $authUser, BpjsDependent $bpjsDependent): bool
    {
        return $authUser->can('View:BpjsDependent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BpjsDependent');
    }

    public function update(AuthUser $authUser, BpjsDependent $bpjsDependent): bool
    {
        return $authUser->can('Update:BpjsDependent');
    }

    public function delete(AuthUser $authUser, BpjsDependent $bpjsDependent): bool
    {
        return $authUser->can('Delete:BpjsDependent');
    }

    public function restore(AuthUser $authUser, BpjsDependent $bpjsDependent): bool
    {
        return $authUser->can('Restore:BpjsDependent');
    }

    public function forceDelete(AuthUser $authUser, BpjsDependent $bpjsDependent): bool
    {
        return $authUser->can('ForceDelete:BpjsDependent');
    }

}