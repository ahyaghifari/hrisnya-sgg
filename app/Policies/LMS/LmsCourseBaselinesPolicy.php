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

    public function restore(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('Restore:LmsCourseBaselines');
    }

    public function forceDelete(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('ForceDelete:LmsCourseBaselines');
    }

    public function restoreAny(AuthUser $authUser, LmsCourseBaselines $lmsCourseBaselines): bool
    {
        return $authUser->can('RestoreAny:LmsCourseBaselines');
    }

}