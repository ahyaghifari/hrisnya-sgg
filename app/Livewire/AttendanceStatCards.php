<?php

namespace App\Livewire;

use App\Services\Attendance\AttendaceReqService;
use Livewire\Component;

class AttendanceStatCards extends Component
{
    public function render(AttendaceReqService $service)
    {
        $requests = $service->getListAttendanceRequestsByUserId(auth()->id());

        $pending = $requests->whereIn('status', ['submitted', 'in_review'])->count();
        $approvedThisMonth = $requests->filter(
            fn ($r) => $r->status === 'approved' && $r->created_at?->isCurrentMonth()
        )->count();

        return view('livewire.attendance-stat-cards', [
            'pending' => $pending,
            'approvedThisMonth' => $approvedThisMonth,
        ]);
    }
}
