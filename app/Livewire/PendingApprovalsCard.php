<?php

namespace App\Livewire;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Models\Attendance\AttendanceRequests;
use App\Models\User\UserTransition;
use App\Services\Approval\ApprovalPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PendingApprovalsCard extends Component
{
    public function render()
    {
        $permissionService = app(ApprovalPermissionService::class);

        $attendanceIds = $permissionService
            ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
            ->pluck('snapshot.module_id')->filter()->values();

        $transitionIds = $permissionService
            ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::USER_TRANSITION->value)
            ->pluck('snapshot.module_id')->filter()->values();

        // user_id nullable (nullOnDelete) - kalau user pengaju sudah dihapus, jangan tampilkan
        // baris ini (tidak ada nama pemohon buat ditampilkan), daripada crash null->name.
        $attendanceRequests = AttendanceRequests::with(['user', 'leaveDetails', 'permissionDetails', 'overtimeDetails'])
            ->whereIn('id', $attendanceIds)
            ->whereHas('user')
            ->get()
            ->map(fn ($request) => (object) [
                'module' => 'attendance_request',
                'badge' => 'yellow',
                'label' => match ($request->type) {
                    'leave' => 'Cuti',
                    'permission' => 'Izin',
                    'overtime' => 'Lembur',
                    default => $request->type,
                },
                'icon' => match ($request->type) {
                    'leave' => 'fa-umbrella-beach',
                    'permission' => 'fa-clock',
                    'overtime' => 'fa-briefcase-clock',
                    default => 'fa-file-lines',
                },
                'tile' => match ($request->type) {
                    'leave' => 'neutral',
                    'permission' => 'warn',
                    'overtime' => 'done',
                    default => 'neutral',
                },
                'keterangan' => match ($request->type) {
                    'leave' => $request->leaveDetails->first()?->leave_type ?? '-',
                    'permission' => $request->permissionDetails->first()?->permission_type ?? '-',
                    'overtime' => $request->overtimeDetails->first()?->overtime_type ?? '-',
                    default => '-',
                },
                'user_name' => $request->user->name,
                'created_at' => $request->created_at,
                'url' => route('my-attendance-requests.show', ['record' => $request->id]),
            ]);

        $transitions = UserTransition::with('user')
            ->whereIn('id', $transitionIds)
            ->whereHas('user')
            ->get()
            ->map(fn ($transition) => (object) [
                'module' => 'user_transition',
                'badge' => 'blue',
                'label' => UserTransitionTypeEnum::from($transition->transition_type)->label(),
                'icon' => match ($transition->transition_type) {
                    'promotion' => 'fa-arrow-up',
                    'demotion' => 'fa-arrow-down',
                    'mutation' => 'fa-building',
                    'termination' => 'fa-user-slash',
                    'kelas_change' => 'fa-arrow-trend-up',
                    default => 'fa-right-left',
                },
                'tile' => 'neutral',
                'keterangan' => $transition->number,
                'user_name' => $transition->user->name,
                'created_at' => $transition->created_at,
                'url' => route('kepegawaian.mutasi-karyawan.show', $transition),
            ]);

        $requests = $attendanceRequests->concat($transitions)->sortByDesc('created_at')->values();

        return view('livewire.pending-approvals-card', ['requests' => $requests]);
    }
}
