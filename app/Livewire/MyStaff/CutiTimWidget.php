<?php

namespace App\Livewire\MyStaff;

use App\Livewire\MyStaff\Concerns\LazyLoadsOnExpand;
use App\Models\Attendance\AttendanceRequests;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Cuti Tim 7 Hari ke Depan" di halaman MyStaff - cuti staff yang approved & mulai
 * dalam 7 hari ke depan, biar atasan bisa antisipasi kekurangan orang. Scope SELALU staff
 * (OrgHierarchyService::subordinates()).
 *
 * Data di-query pas tab "Dashboard" diklik pertama kali - lihat LazyLoadsOnExpand.
 */
class CutiTimWidget extends Component
{
    use LazyLoadsOnExpand;

    public function getUpcomingLeaveProperty()
    {
        $userIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');
        $today = now()->toDateString();
        $horizon = now()->addDays(7)->toDateString();

        return AttendanceRequests::whereIn('user_id', $userIds)
            ->where('type', 'leave')
            ->where('status', 'approved')
            ->whereHas('leaveDetails', fn ($q) => $q->whereBetween('start_date', [$today, $horizon]))
            ->whereHas('user')
            ->with(['user', 'leaveDetails'])
            ->get()
            ->sortBy(fn ($request) => $request->leaveDetails->first()?->start_date)
            ->values();
    }

    public function render()
    {
        return view('livewire.my-staff.cuti-tim-widget');
    }
}
