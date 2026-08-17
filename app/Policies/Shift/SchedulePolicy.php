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
        // Karyawan yang beneran dipakaikan jadwal ini boleh lihat halamannya sendiri (roster
        // shift + teman-teman se-jadwal) walau bukan admin - lihat User::isAssignedToSchedule()
        // (2026-08-15). Aksi edit/generate/import tetap digerbangi KELOLA_JADWAL_SHIFT terpisah
        // di ScheduleShiftRosterTab, bukan dilonggarkan di sini.
        if ($authUser->can('View:Schedule')) {
            return true;
        }

        return method_exists($authUser, 'isAssignedToSchedule') && $authUser->isAssignedToSchedule($schedule);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Schedule');
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