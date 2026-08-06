<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\UserKoperasi;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserKoperasiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserKoperasi');
    }

    public function view(AuthUser $authUser, UserKoperasi $userKoperasi): bool
    {
        return $authUser->can('View:UserKoperasi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserKoperasi');
    }

    public function update(AuthUser $authUser, UserKoperasi $userKoperasi): bool
    {
        return $authUser->can('Update:UserKoperasi');
    }

    public function delete(AuthUser $authUser, UserKoperasi $userKoperasi): bool
    {
        return $authUser->can('Delete:UserKoperasi');
    }

    public function restore(AuthUser $authUser, UserKoperasi $userKoperasi): bool
    {
        return $authUser->can('Restore:UserKoperasi');
    }

    public function forceDelete(AuthUser $authUser, UserKoperasi $userKoperasi): bool
    {
        return $authUser->can('ForceDelete:UserKoperasi');
    }

    public function restoreAny(AuthUser $authUser, UserKoperasi $userKoperasi): bool
    {
        return $authUser->can('RestoreAny:UserKoperasi');
    }

}