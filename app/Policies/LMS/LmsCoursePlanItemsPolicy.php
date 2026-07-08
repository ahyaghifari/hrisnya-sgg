<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsCoursePlanItems;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsCoursePlanItemsPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, LmsCoursePlanItems $lmsCoursePlanItems): bool
    {
        return $authUser->can('View:LmsCoursePlanItems');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsCoursePlanItems');
    }

    public function update(AuthUser $authUser, LmsCoursePlanItems $lmsCoursePlanItems): bool
    {
        return $authUser->can('Update:LmsCoursePlanItems');
    }

    public function delete(AuthUser $authUser, LmsCoursePlanItems $lmsCoursePlanItems): bool
    {
        return $authUser->can('Delete:LmsCoursePlanItems');
    }

}