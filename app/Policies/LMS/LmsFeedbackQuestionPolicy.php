<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsFeedbackQuestion;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsFeedbackQuestionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LmsFeedbackQuestion');
    }

    public function view(AuthUser $authUser, LmsFeedbackQuestion $lmsFeedbackQuestion): bool
    {
        return $authUser->can('View:LmsFeedbackQuestion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsFeedbackQuestion');
    }

    public function update(AuthUser $authUser, LmsFeedbackQuestion $lmsFeedbackQuestion): bool
    {
        return $authUser->can('Update:LmsFeedbackQuestion');
    }

    public function delete(AuthUser $authUser, LmsFeedbackQuestion $lmsFeedbackQuestion): bool
    {
        return $authUser->can('Delete:LmsFeedbackQuestion');
    }

    public function restore(AuthUser $authUser, LmsFeedbackQuestion $lmsFeedbackQuestion): bool
    {
        return $authUser->can('Restore:LmsFeedbackQuestion');
    }

    public function forceDelete(AuthUser $authUser, LmsFeedbackQuestion $lmsFeedbackQuestion): bool
    {
        return $authUser->can('ForceDelete:LmsFeedbackQuestion');
    }

    public function restoreAny(AuthUser $authUser, LmsFeedbackQuestion $lmsFeedbackQuestion): bool
    {
        return $authUser->can('RestoreAny:LmsFeedbackQuestion');
    }

    public function replicate(AuthUser $authUser, LmsFeedbackQuestion $lmsFeedbackQuestion): bool
    {
        return $authUser->can('Replicate:LmsFeedbackQuestion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LmsFeedbackQuestion');
    }

}