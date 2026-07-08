<?php

declare(strict_types=1);

namespace App\Policies\DoctorService;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DoctorService\DoctorFeeSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;

class DoctorFeeSubmissionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DoctorFeeSubmission');
    }

    public function view(AuthUser $authUser, DoctorFeeSubmission $doctorFeeSubmission): bool
    {
        return $authUser->can('View:DoctorFeeSubmission');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DoctorFeeSubmission');
    }

    public function update(AuthUser $authUser, DoctorFeeSubmission $doctorFeeSubmission): bool
    {
        return $authUser->can('Update:DoctorFeeSubmission');
    }

    public function delete(AuthUser $authUser, DoctorFeeSubmission $doctorFeeSubmission): bool
    {
        return $authUser->can('Delete:DoctorFeeSubmission');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DoctorFeeSubmission');
    }

    public function restore(AuthUser $authUser, DoctorFeeSubmission $doctorFeeSubmission): bool
    {
        return $authUser->can('Restore:DoctorFeeSubmission');
    }

    public function forceDelete(AuthUser $authUser, DoctorFeeSubmission $doctorFeeSubmission): bool
    {
        return $authUser->can('ForceDelete:DoctorFeeSubmission');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DoctorFeeSubmission');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DoctorFeeSubmission');
    }

    public function replicate(AuthUser $authUser, DoctorFeeSubmission $doctorFeeSubmission): bool
    {
        return $authUser->can('Replicate:DoctorFeeSubmission');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DoctorFeeSubmission');
    }

}