<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\QuestionBanks;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionBanksPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:QuestionBanks');
    }

    public function view(AuthUser $authUser, QuestionBanks $questionBanks): bool
    {
        return $authUser->can('View:QuestionBanks');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:QuestionBanks');
    }

    public function update(AuthUser $authUser, QuestionBanks $questionBanks): bool
    {
        return $authUser->can('Update:QuestionBanks');
    }

    public function delete(AuthUser $authUser, QuestionBanks $questionBanks): bool
    {
        return $authUser->can('Delete:QuestionBanks');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:QuestionBanks');
    }

    public function restore(AuthUser $authUser, QuestionBanks $questionBanks): bool
    {
        return $authUser->can('Restore:QuestionBanks');
    }

    public function forceDelete(AuthUser $authUser, QuestionBanks $questionBanks): bool
    {
        return $authUser->can('ForceDelete:QuestionBanks');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:QuestionBanks');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:QuestionBanks');
    }

    public function replicate(AuthUser $authUser, QuestionBanks $questionBanks): bool
    {
        return $authUser->can('Replicate:QuestionBanks');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:QuestionBanks');
    }

}