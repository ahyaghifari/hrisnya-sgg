<?php

declare(strict_types=1);

namespace App\Policies\Job;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Job\ManpowerRequests;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManpowerRequestsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ManpowerRequests');
    }

    public function view(AuthUser $authUser, ManpowerRequests $manpowerRequests): bool
    {
        return $authUser->can('View:ManpowerRequests');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ManpowerRequests');
    }

    public function update(AuthUser $authUser, ManpowerRequests $manpowerRequests): bool
    {
        return $authUser->can('Update:ManpowerRequests');
    }

    public function delete(AuthUser $authUser, ManpowerRequests $manpowerRequests): bool
    {
        return $authUser->can('Delete:ManpowerRequests');
    }

    public function restore(AuthUser $authUser, ManpowerRequests $manpowerRequests): bool
    {
        return $authUser->can('Restore:ManpowerRequests');
    }

    public function forceDelete(AuthUser $authUser, ManpowerRequests $manpowerRequests): bool
    {
        return $authUser->can('ForceDelete:ManpowerRequests');
    }

    public function restoreAny(AuthUser $authUser, ManpowerRequests $manpowerRequests): bool
    {
        return $authUser->can('RestoreAny:ManpowerRequests');
    }

}