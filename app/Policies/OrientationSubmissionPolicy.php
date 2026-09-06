<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OrientationSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrientationSubmissionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrientationSubmission');
    }

    public function view(AuthUser $authUser, OrientationSubmission $orientationSubmission): bool
    {
        return $authUser->can('View:OrientationSubmission');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrientationSubmission');
    }

    public function update(AuthUser $authUser, OrientationSubmission $orientationSubmission): bool
    {
        return $authUser->can('Update:OrientationSubmission');
    }

    public function delete(AuthUser $authUser, OrientationSubmission $orientationSubmission): bool
    {
        return $authUser->can('Delete:OrientationSubmission');
    }

    public function restore(AuthUser $authUser, OrientationSubmission $orientationSubmission): bool
    {
        return $authUser->can('Restore:OrientationSubmission');
    }

    public function forceDelete(AuthUser $authUser, OrientationSubmission $orientationSubmission): bool
    {
        return $authUser->can('ForceDelete:OrientationSubmission');
    }

}