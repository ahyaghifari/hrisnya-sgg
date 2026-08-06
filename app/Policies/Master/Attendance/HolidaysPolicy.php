<?php

declare(strict_types=1);

namespace App\Policies\Master\Attendance;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Attendance\Holidays;
use Illuminate\Auth\Access\HandlesAuthorization;

class HolidaysPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Holidays');
    }

    public function view(AuthUser $authUser, Holidays $holidays): bool
    {
        return $authUser->can('View:Holidays');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Holidays');
    }

    public function update(AuthUser $authUser, Holidays $holidays): bool
    {
        return $authUser->can('Update:Holidays');
    }

    public function delete(AuthUser $authUser, Holidays $holidays): bool
    {
        return $authUser->can('Delete:Holidays');
    }

    public function restore(AuthUser $authUser, Holidays $holidays): bool
    {
        return $authUser->can('Restore:Holidays');
    }

}