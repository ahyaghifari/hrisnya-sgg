<?php

namespace App\Livewire\MyStaff;

use App\Livewire\MyStaff\Concerns\LazyLoadsOnExpand;
use App\Models\Coaching\Coaching;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Coaching Tim" di halaman MyStaff - coaching yang orangnya (bukan coach-nya) adalah
 * staff (OrgHierarchyService::subordinates()) - sesuai prinsip semua widget MyStaff "cuma
 * berhubungan dengan staff-nya", bukan dibatasi ke coach_by = user login (atasan bisa saja
 * ingin lihat semua coaching staff-nya walau yang jadi coach orang lain).
 *
 * Data di-query pas tab "Dashboard" diklik pertama kali - lihat LazyLoadsOnExpand.
 */
class CoachingTimWidget extends Component
{
    use LazyLoadsOnExpand;

    public function getTotalProperty(): int
    {
        $userIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');

        return Coaching::query()->whereIn('user_id', $userIds)->count();
    }

    public function getUpcomingDueProperty()
    {
        $userIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');

        return Coaching::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('improvement_due_date', '>=', today())
            ->with(['user', 'coachBy'])
            ->orderBy('improvement_due_date')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.my-staff.coaching-tim-widget');
    }
}
