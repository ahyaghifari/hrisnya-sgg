<?php

namespace App\Livewire;

use App\Http\Resources\Attendance\ListReqResource;
use App\Services\Attendance\AttendaceReqService;
use Livewire\Component;

class RecentRequestsCard extends Component
{
    public function render(AttendaceReqService $service)
    {
        $requests = $service->getListAttendanceRequestsByUserId(auth()->id());

        // Repository sudah order by created_at desc, tinggal ambil 5 teratas.
        $rows = $requests
            ->take(5)
            ->map(fn ($r) => (new ListReqResource($r))->toArray(request()));

        return view('livewire.recent-requests-card', ['rows' => $rows]);
    }
}
