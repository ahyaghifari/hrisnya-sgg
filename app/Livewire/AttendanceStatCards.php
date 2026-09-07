<?php

namespace App\Livewire;

use App\Services\Attendance\AttendaceReqService;
use Livewire\Component;

class AttendanceStatCards extends Component
{
    public bool $compact = false;

    // Default diri sendiri; halaman profil karyawan (MyStaff/Kepegawaian) mengoper id orang lain.
    public ?string $userId = null;

    public function render(AttendaceReqService $service)
    {
        $requests = $service->getListAttendanceRequestsByUserId($this->userId ?? auth()->id());

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
