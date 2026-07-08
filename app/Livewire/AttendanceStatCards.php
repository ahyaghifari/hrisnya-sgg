<?php

namespace App\Livewire;

use App\Services\Attendance\AttendaceReqService;
use Livewire\Component;

class AttendanceStatCards extends Component
{
    public bool $compact = false;

    public function render(AttendaceReqService $service)
    {
        $requests = $service->getListAttendanceRequestsByUserId(auth()->id());

        $izinCount = $requests->filter(
            fn ($r) => $r->type === 'permission' && $r->created_at?->isCurrentMonth()
        )->count();

        $lemburCount = $requests->filter(
            fn ($r) => $r->type === 'overtime' && $r->created_at?->isCurrentMonth()
        )->count();

        return view('livewire.attendance-stat-cards', [
            'izinCount' => $izinCount,
            'lemburCount' => $lemburCount,
        ]);
    }
}
