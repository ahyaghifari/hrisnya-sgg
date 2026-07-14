<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsCourses;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsCoursesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LmsCourses');
    }

    public function view(AuthUser $authUser, LmsCourses $lmsCourses): bool
    {
        return $authUser->can('View:LmsCourses');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsCourses');
    }

    public function update(AuthUser $authUser, LmsCourses $lmsCourses): bool
    {
        return $authUser->can('Update:LmsCourses');
    }

    public function delete(AuthUser $authUser, LmsCourses $lmsCourses): bool
    {
        return $authUser->can('Delete:LmsCourses');
    }

    public function restore(AuthUser $authUser, LmsCourses $lmsCourses): bool
    {
        return $authUser->can('Restore:LmsCourses');
    }

    public function forceDelete(AuthUser $authUser, LmsCourses $lmsCourses): bool
    {
        return $authUser->can('ForceDelete:LmsCourses');
    }

    public function restoreAny(AuthUser $authUser, LmsCourses $lmsCourses): bool
    {
        return $authUser->can('RestoreAny:LmsCourses');
    }

    public function replicate(AuthUser $authUser, LmsCourses $lmsCourses): bool
    {
        return $authUser->can('Replicate:LmsCourses');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LmsCourses');
    }

}