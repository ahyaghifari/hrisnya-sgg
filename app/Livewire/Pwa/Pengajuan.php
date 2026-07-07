<?php

namespace App\Livewire\Pwa;

use App\Models\Attendance\AttendanceRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.pwa-layout', ['title' => 'Pengajuan'])]
class Pengajuan extends Component
{
    use WithPagination;

    public function getRequestsProperty()
    {
        return AttendanceRequests::query()
            ->where('user_id', Auth::id())
            ->with(['leaveDetails', 'permissionDetails', 'overtimeDetails'])
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    public function keterangan(AttendanceRequests $record): string
    {
        return match ($record->type) {
            'leave' => $record->leaveDetails->first()?->leave_type ?? 'Cuti',
            'permission' => $record->permissionDetails->first()?->permission_type ?? 'Izin',
            'overtime' => $record->overtimeDetails->first()?->overtime_type ?? 'Lembur',
            default => '-',
        };
    }

    public function render()
    {
        return view('livewire.pwa.pengajuan');
    }
}
