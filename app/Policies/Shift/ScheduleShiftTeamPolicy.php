<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use App\Models\Shift\ScheduleShiftTeam;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ScheduleShiftTeamPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ScheduleShiftTeam');
    }

    public function view(AuthUser $authUser, ScheduleShiftTeam $scheduleShiftTeam): bool
    {
        return $authUser->can('View:ScheduleShiftTeam');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ScheduleShiftTeam');
    }

    public function update(AuthUser $authUser, ScheduleShiftTeam $scheduleShiftTeam): bool
    {
        return $authUser->can('Update:ScheduleShiftTeam');
    }

    public function delete(AuthUser $authUser, ScheduleShiftTeam $scheduleShiftTeam): bool
    {
        return $authUser->can('Delete:ScheduleShiftTeam');
    }
}
