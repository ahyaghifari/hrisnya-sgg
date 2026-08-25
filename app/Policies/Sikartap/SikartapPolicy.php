<?php

declare(strict_types=1);

namespace App\Policies\Sikartap;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Sikartap\Sikartap;
use Illuminate\Auth\Access\HandlesAuthorization;

class SikartapPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sikartap');
    }

    public function view(AuthUser $authUser, Sikartap $sikartap): bool
    {
        return $authUser->can('View:Sikartap');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sikartap');
    }

    public function update(AuthUser $authUser, Sikartap $sikartap): bool
    {
        return $authUser->can('Update:Sikartap');
    }

    public function delete(AuthUser $authUser, Sikartap $sikartap): bool
    {
        return $authUser->can('Delete:Sikartap');
    }

    public function restore(AuthUser $authUser, Sikartap $sikartap): bool
    {
        return $authUser->can('Restore:Sikartap');
    }

    public function forceDelete(AuthUser $authUser, Sikartap $sikartap): bool
    {
        return $authUser->can('ForceDelete:Sikartap');
    }

}