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

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LmsQuizzes');
    }

    public function restore(AuthUser $authUser, LmsQuizzes $lmsQuizzes): bool
    {
        return $authUser->can('Restore:LmsQuizzes');
    }

    public function forceDelete(AuthUser $authUser, LmsQuizzes $lmsQuizzes): bool
    {
        return $authUser->can('ForceDelete:LmsQuizzes');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LmsQuizzes');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LmsQuizzes');
    }

    public function replicate(AuthUser $authUser, LmsQuizzes $lmsQuizzes): bool
    {
        return $authUser->can('Replicate:LmsQuizzes');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LmsQuizzes');
    }

}