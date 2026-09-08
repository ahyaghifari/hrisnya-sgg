<?php

namespace App\Livewire;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\JobPositionReqStatus;
use App\Enum\UserTransitionTypeEnum;
use App\Enum\ManpowerRequestType;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Job\ManpowerRequests;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\TrainingRequests;
use App\Models\Master\JobPositions;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Models\User\UserTransition;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\JobPosition\ManPowerReqService;
use App\Services\LMS\CourseUserService;
use App\Services\User\UserTransitionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
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

    public bool $collapsed = false;

    /** Baris terceklis manual, key komposit "{dispatch}:{id}" (lihat buildRequests()) - dipakai
     * kalau $bulkTypeFilter kosong (mode manual). */
    public array $selected = [];

    /** Kalau diisi salah satu value 'module' (mis. 'attendance_request'), approve massal
     * mengabaikan $selected dan memproses SEMUA baris jenis itu yang lagi pending & didukung
     * bulk - instruksi user: "jika jenis dipilih berarti semua persetujuan akan di approve,
     * jika tidak berarti hanya yang dipilih nya saja". */
    public string $bulkTypeFilter = '';

    public bool $showBulkConfirm = false;

    public string $bulkNote = '';

    public function toggleCollapsed(): void
    {
        $this->collapsed = ! $this->collapsed;
    }

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

    /** @return Collection<int, object> */
    protected function buildRequests(): Collection
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
                    'id' => $request->id,
                    'dispatch' => 'attendance_request',
                    'bulk_supported' => true,
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
                    'id' => $transition->id,
                    'dispatch' => 'user_transition',
                    'bulk_supported' => true,
                    'module' => 'user_transition',
                    'badge' => 'blue',
                    'label' => UserTransitionTypeEnum::from($transition->transition_type)->label(),
                    'icon' => match ($transition->transition_type) {
                        'promotion' => 'fa-arrow-up',
                        'demotion' => 'fa-arrow-down',
                        'mutation' => 'fa-right-left',
                        'termination' => 'fa-user-slash',
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
                    'dispatch' => 'lms_course_enrollment',
                    'bulk_supported' => true,
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
                    'id' => $request->id,
                    'dispatch' => 'training_request',
                    'bulk_supported' => true,
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
                    'id' => $request->id,
                    'dispatch' => 'manpower_request',
                    'bulk_supported' => true,
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
                    'id' => $jobPosition->id,
                    // Approve/reject sekarang ADA (EditJabatanPage, 2026-08-10), tapi sengaja
                    // tidak didukung approve massal - approve butuh input "realisasi kuota
                    // karyawan" per baris (JobPositionService::approveJobPosition()), tidak ada
                    // 1 nilai default yang aman dipukul rata ke semua baris kayak note massal.
                    'dispatch' => 'jobposition_request',
                    'bulk_supported' => false,
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
                    'id' => $request->id,
                    'dispatch' => 'manpower_request',
                    'bulk_supported' => true,
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

        $shiftSwapRequests = collect();
        if ($this->wants('shift_swap_request')) {
            $swapIds = $permissionService
                ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value)
                // Step 1 SELALU rekan yang jadwalnya mau ditukar (lihat
                // ShiftSwapRequestService::createApprovalSnapshot() - target_id disisipkan sebagai
                // approver pertama) - dikecualikan dari sini, widget ini murni buat approval
                // berbasis manajemen/atasan (instruksi user 2026-08-20). Punya widget sendiri,
                // lihat App\Livewire\Presensi\ShiftSwapApprovalsCard.
                ->reject(fn ($activity) => (int) $activity->current_step_order === 1)
                ->pluck('snapshot.module_id')->filter()->values();

            $shiftSwapRequests = ShiftSwapRequests::with(['requester', 'target', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
                ->whereIn('id', $swapIds)
                ->whereHas('requester')
                ->get()
                ->map(fn ($r) => (object) [
                    'id' => $r->id,
                    'dispatch' => 'shift_swap_request',
                    'bulk_supported' => true,
                    'module' => 'shift_swap_request',
                    'badge' => 'blue',
                    'label' => 'Tukar Shift',
                    'icon' => 'fa-right-left',
                    'tile' => 'neutral',
                    'keterangan' => $r->reqSchedule?->shiftType?->name . ' (' . $r->req_date->translatedFormat('d M') . ')'
                        . ($r->targetSchedule
                            ? ' ↔ ' . $r->targetSchedule->shiftType?->name . ' (' . $r->target_date->translatedFormat('d M') . ')'
                            : ' → diserahkan ke ' . ($r->target?->name ?? '-')),
                    'user_name' => $r->requester->name,
                    'created_at' => $r->created_at,
                    'url' => route('tukar-shift.show', ['record' => $r->id]),
                    'modal' => false,
                    'external' => false,
                ]);
        }

        // Sikartap (Keputusan Direktur) - LEPAS Approval Engine (2026-09-07). Pending = baris
        // Sikartap status 'diajukan' yang Direktur login (pemegang jabatan direktur) boleh
        // putuskan. Dikelompokkan per HRD pengaju (keputusan_direktur_diajukan_by): 1 pengaju
        // 1 orang -> ke detail; >1 -> ke halaman pilih massal (DirekturBulkKeputusanPage).
        $sikartapRequests = collect();
        if ($this->wants('sikartap_director_decision')) {
            $directorService = app(\App\Services\Sikartap\SikartapDirectorDecisionService::class);

            $pendingSikartap = \App\Models\Sikartap\Sikartap::query()
                ->where('status_keputusan_direktur', 'diajukan')
                ->whereHas('user')
                ->with('user')
                ->get()
                ->filter(fn ($s) => $directorService->canDecide($s, Auth::user()))
                ->values();

            foreach ($pendingSikartap->groupBy('keputusan_direktur_diajukan_by') as $submitterId => $group) {
                $sikartapIds = $group->pluck('id')->values();
                $submitter = $submitterId ? User::find($submitterId) : null;
                $latestCreatedAt = $group->pluck('keputusan_direktur_diajukan_at')->filter()->sort()->last();

                $sikartapRequests->push((object) [
                    'id' => $sikartapIds->count() === 1 ? $sikartapIds->first() : ($submitterId ?? 'unknown'),
                    'dispatch' => 'sikartap_director_decision',
                    'bulk_supported' => false,
                    'module' => 'sikartap_director_decision',
                    'badge' => 'purple',
                    'label' => 'Sikartap',
                    'icon' => 'fa-user-check',
                    'tile' => 'neutral',
                    'keterangan' => $sikartapIds->count() === 1
                        ? '1 orang diajukan'
                        : $sikartapIds->count() . ' orang diajukan',
                    'user_name' => $submitter?->name ?? '-',
                    'created_at' => $latestCreatedAt ?? now(),
                    'url' => $sikartapIds->count() === 1
                        ? route('sikartap.pengajuan.show', $sikartapIds->first())
                        : route('sikartap.direktur.bulk'),
                    'modal' => false,
                    'external' => false,
                ]);
            }
        }

        // Penilaian Orientasi - 1 baris per pengajuan. bulk_supported=false: penilai mengisi
        // form skor + keputusan di halaman detail, tidak ada "approve 1 tombol". TIDAK lewat
        // Approval Engine (2026-09-06) - penilai di-resolve dari sikartap_reviewers.
        $orientationRequests = collect();
        if ($this->wants('orientation_request')) {
            $orientationRequests = \App\Models\OrientationSubmission::with(['user', 'userOrientation'])
                ->where('status', 'diajukan')
                ->whereHas('user')
                ->get()
                ->filter(fn ($s) => app(\App\Services\OrientationService::class)->canReview($s, Auth::user()))
                ->values()
                ->map(fn ($s) => (object) [
                    'id' => $s->id,
                    'dispatch' => 'orientation_request',
                    'bulk_supported' => false,
                    'module' => 'orientation_request',
                    'badge' => 'blue',
                    'label' => 'Penilaian Orientasi',
                    'icon' => 'fa-user-graduate',
                    'tile' => 'neutral',
                    'keterangan' => 'Masa orientasi',
                    'user_name' => $s->user->name,
                    'created_at' => $s->submitted_at ?? $s->created_at,
                    'url' => route('orientasi.show', $s->id),
                    'modal' => false,
                    'external' => false,
                ]);
        }

        // Perpanjangan Kontrak - 1 baris per pengajuan, pola sama Orientasi (lepas Approval
        // Engine, penilai di-resolve dari sikartap_reviewers context `contract`).
        $contractRequests = collect();
        if ($this->wants('contract_request')) {
            $contractRequests = \App\Models\ContractSubmission::with(['user', 'userContract'])
                ->where('status', 'diajukan')
                ->whereHas('user')
                ->get()
                ->filter(fn ($s) => app(\App\Services\ContractExtensionService::class)->canReview($s, Auth::user()))
                ->values()
                ->map(fn ($s) => (object) [
                    'id' => $s->id,
                    'dispatch' => 'contract_request',
                    'bulk_supported' => false,
                    'module' => 'contract_request',
                    'badge' => 'blue',
                    'label' => 'Perpanjangan Kontrak',
                    'icon' => 'fa-file-signature',
                    'tile' => 'neutral',
                    'keterangan' => 'Kontrak',
                    'user_name' => $s->user->name,
                    'created_at' => $s->submitted_at ?? $s->created_at,
                    'url' => route('sikartap.kontrak', ['openId' => $s->id]),
                    'modal' => false,
                    'external' => false,
                ]);
        }

        $requests = $attendanceRequests->concat($transitions)->concat($courseEnrollments)->concat($trainingRequests)->concat($jabatanKuota)->concat($manpowerRequests)->concat($shiftSwapRequests)->concat($sikartapRequests)->concat($orientationRequests)->concat($contractRequests)->sortByDesc('created_at')->values();

        foreach ($requests as $request) {
            $request->key = $request->dispatch . ':' . $request->id;
        }

        return $requests;
    }

    /** Jenis yang muncul di dropdown "Approve per Jenis" - cuma module yang punya minimal 1
     * baris bulk_supported, biar tidak nawarin jenis yang isinya kosong/tidak didukung. */
    public function getBulkTypeOptionsProperty(): array
    {
        return $this->buildRequests()
            ->where('bulk_supported', true)
            ->unique('module')
            ->pluck('label', 'module')
            ->all();
    }

    /** Pilih jenis di dropdown -> centang semua baris jenis itu ke $selected beneran (bukan
     * cuma logic terpisah di belakang layar) - checkbox tetap wire:model="selected" jadi ikut
     * kelihatan tercentang, tidak cuma ke-disable doang (laporan user 2026-08-10). Pindah balik
     * ke "pilih manual" TIDAK mengosongkan $selected - hasil auto-centang tadi jadi titik awal
     * yang tetap boleh diubah satu-satu. */
    public function updatedBulkTypeFilter(string $value): void
    {
        if ($value === '') {
            return;
        }

        $this->selected = $this->buildRequests()
            ->where('bulk_supported', true)
            ->where('module', $value)
            ->pluck('key')
            ->all();
    }

    public function getSelectedCountProperty(): int
    {
        return count($this->selected);
    }

    public function openBulkConfirm(): void
    {
        if ($this->selectedCount === 0) {
            return;
        }

        $this->bulkNote = '';
        $this->showBulkConfirm = true;
    }

    public function closeBulkConfirm(): void
    {
        $this->showBulkConfirm = false;
    }

    private function approveOne(object $row): void
    {
        $note = trim($this->bulkNote) !== '' ? $this->bulkNote : 'Disetujui massal';

        match ($row->dispatch) {
            'attendance_request' => app(ApprovalFlowService::class)->advanceStep(
                AttendanceRequests::findOrFail($row->id)->approvalSnapshot,
                Auth::id(),
                $note,
                'approved',
            ),
            'training_request' => app(ApprovalFlowService::class)->advanceStep(
                TrainingRequests::findOrFail($row->id)->approvalSnapshot,
                Auth::id(),
                $note,
                'approved',
            ),
            'shift_swap_request' => app(ApprovalFlowService::class)->advanceStep(
                ShiftSwapRequests::findOrFail($row->id)->approvalSnapshot,
                Auth::id(),
                $note,
                'approved',
            ),
            'user_transition' => app(UserTransitionService::class)->approve(
                UserTransition::findOrFail($row->id),
                Auth::id(),
            ),
            'lms_course_enrollment' => app(CourseUserService::class)->approveEnrollment(
                LmsCourseUsers::findOrFail($row->id),
                Auth::user(),
            ),
            'manpower_request' => app(ManPowerReqService::class)->approveManPower(
                manpowerRequests: ManpowerRequests::findOrFail($row->id),
                status: JobPositionReqStatus::APPROVED->value,
                note: $note,
            ),
            default => throw new \RuntimeException('Jenis pengajuan ini belum didukung approve massal.'),
        };
    }

    /** Baris diambil ULANG (bukan dari render sebelumnya) supaya yang sudah selesai diproses
     * orang lain sejak halaman dibuka otomatis ke-skip, bukan dicoba approve dobel. */
    public function confirmBulkApprove(): void
    {
        $targets = $this->buildRequests()
            ->where('bulk_supported', true)
            ->whereIn('key', $this->selected);

        $success = 0;
        $failed = 0;

        foreach ($targets as $row) {
            try {
                $this->approveOne($row);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $this->selected = [];
        $this->bulkTypeFilter = '';
        $this->bulkNote = '';
        $this->showBulkConfirm = false;

        Notification::make()
            ->title($failed > 0 ? "{$success} disetujui, {$failed} gagal diproses" : "{$success} pengajuan berhasil disetujui")
            ->{$failed > 0 ? 'warning' : 'success'}()
            ->send();
    }

    public function render()
    {
        $requests = $this->buildRequests();

        return view('livewire.pending-approvals-card', ['requests' => $requests]);
    }
}
