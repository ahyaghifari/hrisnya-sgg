<?php

declare(strict_types=1);

namespace App\Policies\Master\Attendance;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Attendance\AttendanceDevice;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceDevicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AttendanceDevice');
    }

    public function view(AuthUser $authUser, AttendanceDevice $attendanceDevice): bool
    {
        return $authUser->can('View:AttendanceDevice');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AttendanceDevice');
    }

    public function update(AuthUser $authUser, AttendanceDevice $attendanceDevice): bool
    {
        return $authUser->can('Update:AttendanceDevice');
    }

    public function delete(AuthUser $authUser, AttendanceDevice $attendanceDevice): bool
    {
        return $authUser->can('Delete:AttendanceDevice');
    }

    public function restore(AuthUser $authUser, AttendanceDevice $attendanceDevice): bool
    {
        return $authUser->can('Restore:AttendanceDevice');
    }

    public function forceDelete(AuthUser $authUser, AttendanceDevice $attendanceDevice): bool
    {
        return $authUser->can('ForceDelete:AttendanceDevice');
    }

    public function restoreAny(AuthUser $authUser, AttendanceDevice $attendanceDevice): bool
    {
        return $authUser->can('RestoreAny:AttendanceDevice');
    }

}