<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\MasterShift;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterShiftPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('View:MasterShift');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MasterShift');
    }

    public function update(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('Update:MasterShift');
    }

    public function delete(AuthUser $authUser, MasterShift $masterShift): bool
    {
        return $authUser->can('Delete:MasterShift');
    }

}