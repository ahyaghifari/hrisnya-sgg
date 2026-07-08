<?php

declare(strict_types=1);

namespace App\Policies\LMS;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\QuestionBanks;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionBanksPolicy
{
    use HandlesAuthorization;
    
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

}