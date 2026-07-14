<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Golongans;
use Illuminate\Auth\Access\HandlesAuthorization;

class GolongansPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Golongans');
    }

    public function view(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('View:Golongans');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Golongans');
    }

    public function update(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('Update:Golongans');
    }

    public function delete(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('Delete:Golongans');
    }

    public function restore(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('Restore:Golongans');
    }

    public function forceDelete(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('ForceDelete:Golongans');
    }

    public function restoreAny(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('RestoreAny:Golongans');
    }

    public function replicate(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('Replicate:Golongans');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Golongans');
    }

}