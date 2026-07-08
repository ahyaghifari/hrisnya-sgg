<?php

declare(strict_types=1);

namespace App\Policies\Counseling;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Counseling\Counseling;
use Illuminate\Auth\Access\HandlesAuthorization;

class CounselingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Counseling');
    }

    public function view(AuthUser $authUser, Counseling $counseling): bool
    {
        return $authUser->can('View:Counseling');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Counseling');
    }

    public function update(AuthUser $authUser, Counseling $counseling): bool
    {
        return $authUser->can('Update:Counseling');
    }

    public function delete(AuthUser $authUser, Counseling $counseling): bool
    {
        return $authUser->can('Delete:Counseling');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Counseling');
    }

    public function restore(AuthUser $authUser, Counseling $counseling): bool
    {
        return $authUser->can('Restore:Counseling');
    }

    public function forceDelete(AuthUser $authUser, Counseling $counseling): bool
    {
        return $authUser->can('ForceDelete:Counseling');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Counseling');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Counseling');
    }

    public function replicate(AuthUser $authUser, Counseling $counseling): bool
    {
        return $authUser->can('Replicate:Counseling');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Counseling');
    }

}