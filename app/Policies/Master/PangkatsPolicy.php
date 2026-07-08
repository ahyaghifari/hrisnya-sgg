<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Pangkats;
use Illuminate\Auth\Access\HandlesAuthorization;

class PangkatsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Pangkats');
    }

    public function view(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('View:Pangkats');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pangkats');
    }

    public function update(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('Update:Pangkats');
    }

    public function delete(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('Delete:Pangkats');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Pangkats');
    }

    public function restore(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('Restore:Pangkats');
    }

    public function forceDelete(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('ForceDelete:Pangkats');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Pangkats');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Pangkats');
    }

    public function replicate(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('Replicate:Pangkats');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Pangkats');
    }

}