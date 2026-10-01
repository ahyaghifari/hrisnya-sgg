<?php

namespace App\Livewire\Concerns;

use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\User;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Collection;

/**
 * Dipakai TeamShiftScheduleCard & ExtraShiftRequestModal - "tim" 1 Kepala Ruangan bukan cuma
 * staff struktur jabatan (OrgHierarchyService::subordinates(), rekursif via parent_id), TAPI
 * juga siapa pun yang pakai Schedule (shift) yang SAMA dengan staff-staff itu.
 *
 * Kenapa perlu tambahan ini (instruksi user 2026-08-18, kasus NIP 02022026834): karyawan
 * Orientasi di beberapa RS ditaruh di 1 Jabatan generik company-wide ("Perawat Orientasi",
 * bukan anak dari Jabatan Kepala Ruangan unit manapun di struktur), jadi TIDAK PERNAH kena
 * subordinates() walau penjadwalan shift-nya sendiri sudah benar per unit (ScheduleShiftAssignment
 * dia nunjuk ke Schedule yang sama dengan perawat unit itu). Struktur jabatan tidak menangkap
 * hubungan ini, tapi roster shift-nya iya - jadi dipakai sebagai sinyal kedua.
 */
trait ResolvesShiftTeamMembers
{
    /** @return Collection<int, User> */
    protected function subordinatesWithSharedSchedule(User $user): Collection
    {
        $subordinates = app(OrgHierarchyService::class)->subordinates($user)->get();

        $scheduleIds = ScheduleShiftAssignment::whereIn('user_id', $subordinates->pluck('id'))
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->get()
            ->pluck('shiftType.schedule_id')
            ->filter()
            ->unique();

        if ($scheduleIds->isEmpty()) {
            return $subordinates->sortBy('name')->values();
        }

        $sharedScheduleUserIds = ScheduleShiftAssignment::whereHas('shiftType', fn ($q) => $q->whereIn('schedule_id', $scheduleIds))
            ->distinct()
            ->pluck('user_id');

        $extra = User::whereIn('id', $sharedScheduleUserIds)
            ->whereNotIn('id', $subordinates->pluck('id')->push($user->id))
            ->get();

        return $subordinates->concat($extra)->sortBy('name')->values();
    }
}
