<?php

declare(strict_types=1);

namespace App\Policies\DoctorService;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DoctorService\DoctorFeeSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;

class DoctorFeeSubmissionPolicy
{
    use HandlesAuthorization;
    
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

}