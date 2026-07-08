<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsCategories;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsCategoriesPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, LmsCategories $lmsCategories): bool
    {
        return $authUser->can('View:LmsCategories');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsCategories');
    }

    public function update(AuthUser $authUser, LmsCategories $lmsCategories): bool
    {
        return $authUser->can('Update:LmsCategories');
    }

    public function delete(AuthUser $authUser, LmsCategories $lmsCategories): bool
    {
        return $authUser->can('Delete:LmsCategories');
    }

}