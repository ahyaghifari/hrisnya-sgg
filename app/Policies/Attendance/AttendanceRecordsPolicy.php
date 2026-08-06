<?php

declare(strict_types=1);

namespace App\Policies\Attendance;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Attendance\AttendanceRecords;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceRecordsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AttendanceRecords');
    }

    public function view(AuthUser $authUser, AttendanceRecords $attendanceRecords): bool
    {
        return $authUser->can('View:AttendanceRecords');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AttendanceRecords');
    }

    public function update(AuthUser $authUser, AttendanceRecords $attendanceRecords): bool
    {
        return $authUser->can('Update:AttendanceRecords');
    }

    public function delete(AuthUser $authUser, AttendanceRecords $attendanceRecords): bool
    {
        return $authUser->can('Delete:AttendanceRecords');
    }

    public function restore(AuthUser $authUser, AttendanceRecords $attendanceRecords): bool
    {
        return $authUser->can('Restore:AttendanceRecords');
    }

    public function forceDelete(AuthUser $authUser, AttendanceRecords $attendanceRecords): bool
    {
        return $authUser->can('ForceDelete:AttendanceRecords');
    }

    public function restoreAny(AuthUser $authUser, AttendanceRecords $attendanceRecords): bool
    {
        return $authUser->can('RestoreAny:AttendanceRecords');
    }

}