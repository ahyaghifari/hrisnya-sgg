<?php

declare(strict_types=1);

namespace App\Policies\Attendance;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Attendance\AttendanceRequests;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceRequestsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AttendanceRequests');
    }

    public function view(AuthUser $authUser, AttendanceRequests $attendanceRequests): bool
    {
        return $authUser->can('View:AttendanceRequests');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AttendanceRequests');
    }

    public function update(AuthUser $authUser, AttendanceRequests $attendanceRequests): bool
    {
        return $authUser->can('Update:AttendanceRequests');
    }

    public function delete(AuthUser $authUser, AttendanceRequests $attendanceRequests): bool
    {
        return $authUser->can('Delete:AttendanceRequests');
    }

    public function restore(AuthUser $authUser, AttendanceRequests $attendanceRequests): bool
    {
        return $authUser->can('Restore:AttendanceRequests');
    }

    public function forceDelete(AuthUser $authUser, AttendanceRequests $attendanceRequests): bool
    {
        return $authUser->can('ForceDelete:AttendanceRequests');
    }

    public function restoreAny(AuthUser $authUser, AttendanceRequests $attendanceRequests): bool
    {
        return $authUser->can('RestoreAny:AttendanceRequests');
    }

    public function replicate(AuthUser $authUser, AttendanceRequests $attendanceRequests): bool
    {
        return $authUser->can('Replicate:AttendanceRequests');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AttendanceRequests');
    }

}