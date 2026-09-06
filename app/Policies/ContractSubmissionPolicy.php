<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ContractSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContractSubmissionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContractSubmission');
    }

    public function view(AuthUser $authUser, ContractSubmission $contractSubmission): bool
    {
        return $authUser->can('View:ContractSubmission');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContractSubmission');
    }

    public function update(AuthUser $authUser, ContractSubmission $contractSubmission): bool
    {
        return $authUser->can('Update:ContractSubmission');
    }

    public function delete(AuthUser $authUser, ContractSubmission $contractSubmission): bool
    {
        return $authUser->can('Delete:ContractSubmission');
    }

    public function restore(AuthUser $authUser, ContractSubmission $contractSubmission): bool
    {
        return $authUser->can('Restore:ContractSubmission');
    }

    public function forceDelete(AuthUser $authUser, ContractSubmission $contractSubmission): bool
    {
        return $authUser->can('ForceDelete:ContractSubmission');
    }

}