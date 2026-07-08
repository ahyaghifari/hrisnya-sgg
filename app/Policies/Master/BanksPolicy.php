<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Banks;
use Illuminate\Auth\Access\HandlesAuthorization;

class BanksPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Banks');
    }

    public function view(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('View:Banks');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Banks');
    }

    public function update(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('Update:Banks');
    }

    public function delete(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('Delete:Banks');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Banks');
    }

    public function restore(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('Restore:Banks');
    }

    public function forceDelete(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('ForceDelete:Banks');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Banks');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Banks');
    }

    public function replicate(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('Replicate:Banks');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Banks');
    }

}