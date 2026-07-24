<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use App\Models\Shift\Schedule;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Schedule (modul Jadwal, restrukturisasi 2026-07-18) tidak punya Filament Resource — cuma
 * dikelola lewat portal (CreateSchedulePage, SchedulesPage, ScheduleAssignmentsPage,
 * ScheduleShiftRosterTab, ScheduleShiftRotationTab), jadi Policy-nya ditulis manual di sini,
 * tetap pakai konvensi ability "Verb:Subject" yang sama supaya konsisten & otomatis muncul di
 * RoleForm (/admin/roles). Satu ability 'update' dipakai buat SEMUA aksi ubah-jadwal (roster,
 * penempatan jabatan, template rotasi) — bukan cuma ubah header jadwalnya sendiri — karena
 * semuanya konsep "mengelola jadwal ini", bukan sub-resource yang butuh akses terpisah.
 */
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

    public function update(AuthUser $authUser, ?Schedule $schedule = null): bool
    {
        return $authUser->can('Update:Schedule');
    }

    public function delete(AuthUser $authUser, ?Schedule $schedule = null): bool
    {
        return $authUser->can('Delete:Schedule');
    }
}
