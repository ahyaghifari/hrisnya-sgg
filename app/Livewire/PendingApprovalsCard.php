<?php

namespace App\Livewire;

use App\Models\Attendance\AttendanceRequests;
use App\Services\Approval\ApprovalPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PendingApprovalsCard extends Component
{
    public function render()
    {
        $activities = app(ApprovalPermissionService::class)
            ->pendingApprovalsFor(Auth::user(), 'attendance_request');

        $moduleIds = $activities->pluck('snapshot.module_id')->filter()->values();

        $requests = AttendanceRequests::with(['user', 'leaveDetails', 'permissionDetails', 'overtimeDetails'])
            ->whereIn('id', $moduleIds)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.pending-approvals-card', ['requests' => $requests]);
    }
}
