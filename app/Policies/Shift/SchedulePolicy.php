<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\Schedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Schedule');
    }

    public function view(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('View:Schedule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Schedule');
    }

    /** Duplikat 1 jadwal jadi jadwal baru - permission sendiri (Duplicate:Schedule, bukan
     * reuse Create:Schedule lagi), instruksi user 2026-09-16, lihat migration
     * 2026_09_16_100000_seed_duplicate_schedule_permission.php. */
    public function duplicate(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('Duplicate:Schedule');
    }

    public function update(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('Update:Schedule');
    }

    public function delete(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('Delete:Schedule');
    }

    public function restore(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('Restore:Schedule');
    }

    public function forceDelete(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('ForceDelete:Schedule');
    }

}