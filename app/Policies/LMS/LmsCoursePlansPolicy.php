<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsCoursePlans;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsCoursePlansPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LmsCoursePlans');
    }

    public function view(AuthUser $authUser, LmsCoursePlans $lmsCoursePlans): bool
    {
        return $authUser->can('View:LmsCoursePlans');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsCoursePlans');
    }

    public function update(AuthUser $authUser, LmsCoursePlans $lmsCoursePlans): bool
    {
        return $authUser->can('Update:LmsCoursePlans');
    }

    public function delete(AuthUser $authUser, LmsCoursePlans $lmsCoursePlans): bool
    {
        return $authUser->can('Delete:LmsCoursePlans');
    }

    public function restore(AuthUser $authUser, LmsCoursePlans $lmsCoursePlans): bool
    {
        return $authUser->can('Restore:LmsCoursePlans');
    }

    public function forceDelete(AuthUser $authUser, LmsCoursePlans $lmsCoursePlans): bool
    {
        return $authUser->can('ForceDelete:LmsCoursePlans');
    }

    public function restoreAny(AuthUser $authUser, LmsCoursePlans $lmsCoursePlans): bool
    {
        return $authUser->can('RestoreAny:LmsCoursePlans');
    }

    public function replicate(AuthUser $authUser, LmsCoursePlans $lmsCoursePlans): bool
    {
        return $authUser->can('Replicate:LmsCoursePlans');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LmsCoursePlans');
    }

}