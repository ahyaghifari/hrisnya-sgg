<?php

namespace App\Livewire;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Filament\Resources\LMS\LmsCourses\LmsCoursesResource;
use App\Models\Attendance\AttendanceRequests;
use App\Models\LMS\LmsCourseUsers;
use App\Models\User\UserTransition;
use App\Services\Approval\ApprovalPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PendingApprovalsCard extends Component
{
    /** Batasi modul yang ditampilkan, mis. ['lms_course_enrollment'] kalau kartu ini ditaruh di
     * halaman Pelatihan (cuma relevan tampilkan approval Pelatihan di situ, bukan Izin/Cuti/
     * Lembur/Perubahan Status). Null (default) = semua modul, dipakai di dashboard/MyStaff. */
    public ?array $only = null;

    private function wants(string $module): bool
    {
        return $this->only === null || in_array($module, $this->only, true);
    }

    public function render()
    {
        $permissionService = app(ApprovalPermissionService::class);

        $attendanceRequests = collect();
        if ($this->wants('attendance_request')) {
            $attendanceIds = $permissionService
                ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
                ->pluck('snapshot.module_id')->filter()->values();

            // user_id nullable (nullOnDelete) - kalau user pengaju sudah dihapus, jangan
            // tampilkan baris ini (tidak ada nama pemohon buat ditampilkan), daripada crash
            // null->name.
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
                    'external' => false,
                ]);
        }

        $transitions = collect();
        if ($this->wants('user_transition')) {
            $transitionIds = $permissionService
                ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::USER_TRANSITION->value)
                ->pluck('snapshot.module_id')->filter()->values();

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
                    'external' => false,
                ]);
        }

        // Pelatihan bukan module approval-flow generik (tidak ada snapshot/step) - cuma 1
        // permission global "boleh approve pengajuan pelatihan", jadi kalau punya izinnya,
        // tampilkan SEMUA pengajuan REQUESTED, tidak perlu resolve step approver kayak 2 modul
        // di atas.
        $courseEnrollments = collect();
        if ($this->wants('lms_course_enrollment') && Auth::user()->can(CustomPermissionEnum::APPROVE_PENGAJUAN_PELATIHAN->value)) {
            $courseEnrollments = LmsCourseUsers::where('status', CourseUserStatus::REQUESTED->value)
                ->with(['user', 'course'])
                ->whereHas('user')
                ->get()
                ->map(fn ($enrollment) => (object) [
                    'module' => 'lms_course_enrollment',
                    'badge' => 'purple',
                    'label' => 'Pelatihan',
                    'icon' => 'fa-graduation-cap',
                    'tile' => 'neutral',
                    'keterangan' => $enrollment->course?->title ?? '-',
                    'user_name' => $enrollment->user->name,
                    // enrolled_at bukan Carbon-cast di model (plain string) - parse manual biar
                    // konsisten sama created_at (Carbon) yang dipakai baris lain, dipakai bareng
                    // buat sortByDesc & ->translatedFormat() di blade.
                    'created_at' => $enrollment->enrolled_at ? \Illuminate\Support\Carbon::parse($enrollment->enrolled_at) : $enrollment->created_at,
                    'url' => LmsCoursesResource::getUrl('participants', ['record' => $enrollment->course_id]),
                    // Link keluar dari portal SPA ke admin panel Filament - jangan wire:navigate
                    // (beda app shell sama sekali, morph Livewire bisa rusak), full page load biasa.
                    'external' => true,
                ]);
        }

        $requests = $attendanceRequests->concat($transitions)->concat($courseEnrollments)->sortByDesc('created_at')->values();

        return view('livewire.pending-approvals-card', ['requests' => $requests]);
    }
}
