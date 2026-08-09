<?php

namespace App\Livewire;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Enum\ManpowerRequestType;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Job\ManpowerRequests;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\TrainingRequests;
use App\Models\Master\JobPositions;
use App\Models\User\UserTransition;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\LMS\CourseUserService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PendingApprovalsCard extends Component
{
    /** Batasi modul yang ditampilkan, mis. ['lms_course_enrollment'] kalau kartu ini ditaruh di
     * halaman Pelatihan (cuma relevan tampilkan approval Pelatihan di situ, bukan Izin/Cuti/
     * Lembur/Perubahan Status). Null (default) = semua modul, dipakai di dashboard/MyStaff. */
    public ?array $only = null;

    // Pelatihan approve/reject langsung dari modal di kartu ini - tidak perlu masuk admin panel
    // cuma buat approve satu pengajuan (beda dari Izin/Cuti/Lembur/Perubahan Status yang sudah
    // punya halaman detail portal sendiri buat ditinjau).
    public ?string $reviewingEnrollmentId = null;

    private function wants(string $module): bool
    {
        return $this->only === null || in_array($module, $this->only, true);
    }

    public function openReviewModal(string $enrollmentId): void
    {
        $this->reviewingEnrollmentId = $enrollmentId;
    }

    public function closeReviewModal(): void
    {
        $this->reviewingEnrollmentId = null;
    }

    public function getReviewingEnrollmentProperty(): ?LmsCourseUsers
    {
        return $this->reviewingEnrollmentId
            ? LmsCourseUsers::with(['user', 'course'])->find($this->reviewingEnrollmentId)
            : null;
    }

    public function approveEnrollment(): void
    {
        if (! $this->reviewingEnrollment) {
            return;
        }

        try {
            app(CourseUserService::class)->approveEnrollment($this->reviewingEnrollment, Auth::user());
            Notification::make()->title('Pendaftaran disetujui')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menyetujui pendaftaran ini')->danger()->send();
        }

        $this->reviewingEnrollmentId = null;
    }

    public function rejectEnrollment(): void
    {
        if (! $this->reviewingEnrollment) {
            return;
        }

        try {
            app(CourseUserService::class)->rejectEnrollment($this->reviewingEnrollment, Auth::user());
            Notification::make()->title('Pendaftaran ditolak')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menolak pendaftaran ini')->danger()->send();
        }

        $this->reviewingEnrollmentId = null;
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
                    'modal' => false,
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
                    'modal' => false,
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
                    // Approve/reject lewat modal langsung di kartu ini (bukan link) - lihat
                    // openReviewModal()/approveEnrollment()/rejectEnrollment().
                    'id' => $enrollment->id,
                    'modal' => true,
                    'url' => null,
                    'external' => false,
                ]);
        }

        $trainingRequests = collect();
        if ($this->wants('training_request')) {
            $trainingIds = $permissionService
                ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::TRAINING_REQUEST->value)
                ->pluck('snapshot.module_id')->filter()->values();

            $trainingRequests = TrainingRequests::with('user')
                ->whereIn('id', $trainingIds)
                ->whereHas('user')
                ->get()
                ->map(fn ($request) => (object) [
                    'module' => 'training_request',
                    'badge' => 'purple',
                    'label' => 'Pelatihan Eksternal',
                    'icon' => 'fa-plane-departure',
                    'tile' => 'neutral',
                    'keterangan' => $request->title,
                    'user_name' => $request->user->name,
                    'created_at' => $request->created_at,
                    'url' => route('pelatihan.pengajuan-saya.show', ['record' => $request->id]),
                    'modal' => false,
                    'external' => false,
                ]);
        }

        // Jabatan & Kuota digabung 1 kategori (instruksi user 2026-08-09) - dua module beda
        // (jobposition_request vs additional_quota_request) tapi sama-sama soal Jabatan, jadi
        // ditampilkan sebagai 1 jenis badge. CATATAN: jobposition_request BELUM punya halaman
        // approve/reject di portal (EditJabatanPage cuma view/edit draft, tidak ada tombol
        // approve) - url-nya sementara ke situ, approver belum bisa benar-benar memproses dari
        // sini sampai halaman approve-nya dibangun (beda dari additional_quota_request yang
        // sudah bisa diproses penuh di ManpowerRequestDetailPage).
        $jabatanKuota = collect();
        if ($this->wants('jabatan_kuota')) {
            $quotaIds = $permissionService
                ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::ADDITIONAL_QUOTA_REQUEST->value)
                ->pluck('snapshot.module_id')->filter()->values();

            $quotaRequests = ManpowerRequests::with(['jobPosition', 'user'])
                ->whereIn('id', $quotaIds)
                ->whereHas('user')
                ->get()
                ->map(fn ($request) => (object) [
                    'module' => 'jabatan_kuota',
                    'badge' => 'blue',
                    'label' => 'Jabatan & Kuota',
                    'icon' => 'fa-sitemap',
                    'tile' => 'neutral',
                    'keterangan' => 'Penambahan ' . $request->quantity . ' kuota - ' . ($request->jobPosition?->name ?? '-'),
                    'user_name' => $request->user->name,
                    'created_at' => $request->created_at,
                    'url' => route('kepegawaian.permintaan.show', $request),
                    'modal' => false,
                    'external' => false,
                ]);

            $jobPositionIds = $permissionService
                ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::JOBPOSITION_REQUEST->value)
                ->pluck('snapshot.module_id')->filter()->values();

            $jobPositionRequests = JobPositions::with('requestedBy')
                ->whereIn('id', $jobPositionIds)
                ->whereHas('requestedBy')
                ->get()
                ->map(fn ($jobPosition) => (object) [
                    'module' => 'jabatan_kuota',
                    'badge' => 'blue',
                    'label' => 'Jabatan & Kuota',
                    'icon' => 'fa-sitemap',
                    'tile' => 'neutral',
                    'keterangan' => 'Pengajuan Jabatan Baru - ' . $jobPosition->name,
                    'user_name' => $jobPosition->requestedBy->name,
                    'created_at' => $jobPosition->created_at,
                    'url' => route('kepegawaian.jabatan.edit', $jobPosition),
                    'modal' => false,
                    'external' => false,
                ]);

            $jabatanKuota = $quotaRequests->concat($jobPositionRequests);
        }

        // Permintaan Tenaga Kerja (Tambah Karyawan/Penggantian Karyawan) - beda kategori dari
        // Jabatan & Kuota di atas (instruksi user 2026-08-09). Dua-duanya sudah bisa diproses
        // penuh di ManpowerRequestDetailPage (approve/reject), jadi url-nya langsung jalan,
        // beda dari jobposition_request yang masih stopgap.
        $manpowerRequests = collect();
        if ($this->wants('manpower_request')) {
            $manpowerIds = $permissionService
                ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::ADDITIONAL_EMPLOYEE_REQUEST->value)
                ->concat($permissionService->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::EMPLOYEE_REPLACEMENT_REQUEST->value))
                ->pluck('snapshot.module_id')->filter()->values();

            $manpowerRequests = ManpowerRequests::with(['jobPosition', 'user'])
                ->whereIn('id', $manpowerIds)
                ->whereHas('user')
                ->get()
                ->map(fn ($request) => (object) [
                    'module' => 'manpower_request',
                    'badge' => 'yellow',
                    'label' => 'Permintaan Tenaga Kerja',
                    'icon' => 'fa-people-arrows',
                    'tile' => 'neutral',
                    'keterangan' => ManpowerRequestType::from($request->type)->getLabel() . ' ' . $request->quantity . ' - ' . ($request->jobPosition?->name ?? '-'),
                    'user_name' => $request->user->name,
                    'created_at' => $request->created_at,
                    'url' => route('kepegawaian.permintaan.show', $request),
                    'modal' => false,
                    'external' => false,
                ]);
        }

        $requests = $attendanceRequests->concat($transitions)->concat($courseEnrollments)->concat($trainingRequests)->concat($jabatanKuota)->concat($manpowerRequests)->sortByDesc('created_at')->values();

        return view('livewire.pending-approvals-card', ['requests' => $requests]);
    }
}
