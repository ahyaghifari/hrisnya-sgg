<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\FeeSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeeSubmissionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FeeSubmission');
    }

    public function view(AuthUser $authUser, FeeSubmission $feeSubmission): bool
    {
        return $authUser->can('View:FeeSubmission');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FeeSubmission');
    }

    public function update(AuthUser $authUser, FeeSubmission $feeSubmission): bool
    {
        return $authUser->can('Update:FeeSubmission');
    }

    public function delete(AuthUser $authUser, FeeSubmission $feeSubmission): bool
    {
        return $authUser->can('Delete:FeeSubmission');
    }

    public function restore(AuthUser $authUser, FeeSubmission $feeSubmission): bool
    {
        return $authUser->can('Restore:FeeSubmission');
    }

    public function forceDelete(AuthUser $authUser, FeeSubmission $feeSubmission): bool
    {
        return $authUser->can('ForceDelete:FeeSubmission');
    }

}