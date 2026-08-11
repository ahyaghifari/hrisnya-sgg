<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\LmsQuestionBanks;
use Illuminate\Auth\Access\HandlesAuthorization;

class LmsQuestionBanksPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LmsQuestionBanks');
    }

    public function view(AuthUser $authUser, LmsQuestionBanks $lmsQuestionBanks): bool
    {
        return $authUser->can('View:LmsQuestionBanks');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsQuestionBanks');
    }

    public function update(AuthUser $authUser, LmsQuestionBanks $lmsQuestionBanks): bool
    {
        return $authUser->can('Update:LmsQuestionBanks');
    }

    public function delete(AuthUser $authUser, LmsQuestionBanks $lmsQuestionBanks): bool
    {
        return $authUser->can('Delete:LmsQuestionBanks');
    }

    public function restore(AuthUser $authUser, LmsQuestionBanks $lmsQuestionBanks): bool
    {
        return $authUser->can('Restore:LmsQuestionBanks');
    }

    public function forceDelete(AuthUser $authUser, LmsQuestionBanks $lmsQuestionBanks): bool
    {
        return $authUser->can('ForceDelete:LmsQuestionBanks');
    }

}