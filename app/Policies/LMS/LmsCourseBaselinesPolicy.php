<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsCourseBaselines;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsCourseBaselinesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LmsCourseBaselines');
    }

    public function view(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('View:LmsCourseBaselines');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsCourseBaselines');
    }

    public function update(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('Update:LmsCourseBaselines');
    }

    public function delete(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('Delete:LmsCourseBaselines');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LmsCourseBaselines');
    }

    public function restore(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('Restore:LmsCourseBaselines');
    }

    public function forceDelete(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('ForceDelete:LmsCourseBaselines');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LmsCourseBaselines');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LmsCourseBaselines');
    }

    public function replicate(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('Replicate:LmsCourseBaselines');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LmsCourseBaselines');
    }

}