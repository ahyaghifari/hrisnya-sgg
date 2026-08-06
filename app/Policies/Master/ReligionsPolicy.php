<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Religions;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReligionsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Religions');
    }

    public function view(AuthUser $authUser, Religions $religions): bool
    {
        return $authUser->can('View:Religions');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Religions');
    }

    public function update(AuthUser $authUser, Religions $religions): bool
    {
        return $authUser->can('Update:Religions');
    }

    public function delete(AuthUser $authUser, Religions $religions): bool
    {
        return $authUser->can('Delete:Religions');
    }

    public function restore(AuthUser $authUser, Religions $religions): bool
    {
        return $authUser->can('Restore:Religions');
    }

    public function forceDelete(AuthUser $authUser, Religions $religions): bool
    {
        return $authUser->can('ForceDelete:Religions');
    }

    public function restoreAny(AuthUser $authUser, Religions $religions): bool
    {
        return $authUser->can('RestoreAny:Religions');
    }

}