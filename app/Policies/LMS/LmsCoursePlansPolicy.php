<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsCoursePlans;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsCoursePlansPolicy
{
    use HandlesAuthorization;
    
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

}