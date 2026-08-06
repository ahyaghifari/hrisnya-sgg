<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsQuizzes;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsQuizzesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LmsQuizzes');
    }

    public function view(AuthUser $authUser, LmsQuizzes $lmsQuizzes): bool
    {
        return $authUser->can('View:LmsQuizzes');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsQuizzes');
    }

    public function update(AuthUser $authUser, LmsQuizzes $lmsQuizzes): bool
    {
        return $authUser->can('Update:LmsQuizzes');
    }

    public function delete(AuthUser $authUser, LmsQuizzes $lmsQuizzes): bool
    {
        return $authUser->can('Delete:LmsQuizzes');
    }

    public function restore(AuthUser $authUser, LmsQuizzes $lmsQuizzes): bool
    {
        return $authUser->can('Restore:LmsQuizzes');
    }

}