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

    public function view(AuthUser $authUser, LmsQuestionBanks $questionBanks): bool
    {
        return $authUser->can('View:LmsQuestionBanks');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LmsQuestionBanks');
    }

    public function update(AuthUser $authUser, LmsQuestionBanks $questionBanks): bool
    {
        return $authUser->can('Update:LmsQuestionBanks');
    }

    public function delete(AuthUser $authUser, LmsQuestionBanks $questionBanks): bool
    {
        return $authUser->can('Delete:LmsQuestionBanks');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LmsQuestionBanks');
    }

    public function restore(AuthUser $authUser, LmsQuestionBanks $questionBanks): bool
    {
        return $authUser->can('Restore:LmsQuestionBanks');
    }

    public function forceDelete(AuthUser $authUser, LmsQuestionBanks $questionBanks): bool
    {
        return $authUser->can('ForceDelete:LmsQuestionBanks');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LmsQuestionBanks');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LmsQuestionBanks');
    }

    public function replicate(AuthUser $authUser, LmsQuestionBanks $questionBanks): bool
    {
        return $authUser->can('Replicate:LmsQuestionBanks');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LmsQuestionBanks');
    }

}
