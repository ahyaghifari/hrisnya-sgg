<?php

namespace App\Livewire\MyStaff;

use App\Enum\CoachingStatus;
use App\Livewire\MyStaff\Concerns\LazyLoadsOnExpand;
use App\Models\Coaching\Coaching;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Coaching Tim" di halaman MyStaff - coaching yang orangnya (bukan coach-nya) adalah
 * bawahan (OrgHierarchyService::subordinates()) - sesuai prinsip semua widget MyStaff "cuma
 * berhubungan dengan bawahannya", bukan dibatasi ke coach_by = user login (atasan bisa saja
 * ingin lihat semua coaching bawahannya walau yang jadi coach orang lain).
 *
 * Data di-query pas tab "Dashboard" diklik pertama kali - lihat LazyLoadsOnExpand.
 */
class CoachingTimWidget extends Component
{
    use LazyLoadsOnExpand;

    public function getStatusCountsProperty(): array
    {
        $userIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');

        $counts = Coaching::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(['pending', 'in_progress', 'submitted', 'completed'])
            ->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    public function getUpcomingDueProperty()
    {
        $userIds = app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');

        return Coaching::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', [CoachingStatus::PENDING->value, CoachingStatus::IN_PROGRESS->value])
            ->whereNotNull('improvement_due_date')
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
