<?php

namespace App\Services\Attendance;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Approval\AttendanceApprovalBucketEnum;
use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Setting;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use App\Models\User;
use App\Repositories\Approval\ApprovalHistoryRepository;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Attendance\AttendanceReqRepository;
use App\Repositories\Attendance\LeaveReqRepository;
use App\Repositories\Attendance\OvertimeReqRepository;
use App\Repositories\Attendance\PermissionReqRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendaceReqService
{
    /** Cuti TAHUNAN (submit self-service saja, bukan dibuat admin) wajib diajukan minimal
     *  H-30 - instruksi user 2026-09-28, sengaja HANYA untuk kategori Tahunan (bukan jenis cuti
     *  lain). Sumber kebenaran TUNGGAL (dipakai SubmitLeaveRequest buat `min` date picker DAN
     *  diassert ulang di storeLeaveRequest() sebagai jaring pengaman server-side, termasuk jalur
     *  mobile API) - jangan duplikasi angka 30 ini di tempat lain. */
    public const LEAVE_MIN_LEAD_DAYS = 30;

    public function minLeaveDate(): Carbon
    {
        return now()->addDays(self::LEAVE_MIN_LEAD_DAYS)->startOfDay();
    }

    public function __construct(
        protected AttendanceReqRepository $attendanceReqRepository,
        protected OvertimeReqRepository $overtimeReqRepository,
        protected LeaveReqRepository $leaveReqRepository,
        protected PermissionReqRepository $permissionReqRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalHistoryRepository $approvalHistoryRepository,
        protected NotificationService $notificationService,
        protected ScheduleResolver $scheduleResolver,
        protected AttendanceQuotaCalculator $quotaCalculator,
    ) {}

    public function getMasterAttendanceReqTypes(string $type, ?string $companyId = null)
    {
        return $this->attendanceReqRepository->getMasterAttendanceReqTypes($type, $companyId);
    }

    public function getListAttendanceRequestsByUserId(string $userId, array $filters = [])
    {
        return $this->attendanceReqRepository->getListAttendanceRequestsByUserId($userId, $filters);
    }

    public function findAttendanceRequestById(string $id): object|null
    {
        return $this->attendanceReqRepository->findAttendanceRequestById($id);
    }

    public function getLeaveUserBalance(string $userId, string $typeId, ?string $referenceDate = null): array
    {
        return $this->quotaCalculator->leaveBalance($userId, $typeId, $referenceDate);
    }

    /**
     * Info kuota Izin pemohon per kelompok buat halaman approval / form pengajuan. Delegasi ke
     * AttendanceQuotaCalculator — enforcement tetap di assertWithinIzinPeriodeLimit().
     */
    public function getIzinPeriodeUsage(User $user, PermissionGroupEnum $group, ?string $referenceDate = null): ?array
    {
        return $this->quotaCalculator->izinPeriode($user, $group, $referenceDate);
    }

    public function storeOvertimeRequest(array $data, ?User $actingUser = null, bool $skipApproval = false, ?string $note = null, ?User $createdBy = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $createdBy ??= $actingUser;
        $userId = $actingUser->id;
        $type = 'overtime';
        $status = $skipApproval ? 'approved' : 'in_review';

        $masterFlow = null;
        if (! $skipApproval) {
            $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
                companyId: $actingUser->company_id,
                userId: $userId,
                subModule: AttendanceApprovalBucketEnum::OVERTIME->value
            );

            if (!$masterFlow) {
                throw new \Exception('No active approval flow found for attendance requests for this company.');
            }
        }

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'created_by' => $createdBy->id,
            'type' => $type,
            'status' => $status,
        ]);

        $overtimeTypeName = \App\Enum\Attendance\OvertimeTypeEnum::from($data['type_id'])->label();

        $uploadedFiles = $data['attachments'] ?? null;

        $overtimeData = [
            'request_id' => $request->id,
            'overtime_type' => $overtimeTypeName,
            'date' => $data['date'] ?? null,
            'tasks' => $data['tasks'] ?? null,
            'attachments_files' => $uploadedFiles,
            'task_description' => $data['task_description'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
        ];

        $this->overtimeReqRepository->storeOvertimeRequest($overtimeData);

        if ($skipApproval) {
            $this->attendanceReqRepository->updateAttendanceRequest($request, [
                'approved_by' => $createdBy->id,
                'approved_at' => now(),
            ]);

            $this->approvalHistoryRepository->create([
                'requestable_type' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
                'requestable_id' => $request->id,
                'action' => 'approved',
                'actor_id' => $createdBy->id,
                'actor_name' => $createdBy->name,
                'note' => $note,
            ]);

            return $this->overtimeReqRepository->findAttendanceReqOvertimeDetail($request->id);
        }

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            moduleId: $request->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: $actingUser->company_id,
            userId: $userId
        );

        // Send notification to first approver
        $firstApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
        if ($firstApprover) {
            $this->notificationService->sendOvertimeRequestNotification(
                approver: $firstApprover,
                requestData: [
                    'request_id' => $request->id,
                    'overtime_type' => $overtimeTypeName,
                    'date' => $data['date'] ?? null,
                    'start_time' => $data['start_time'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->overtimeReqRepository->findAttendanceReqOvertimeDetail($request->id);
    }

    /** Fitur pengganti cuti HANYA untuk 2 company RS (Banjarbaru & Barabai) - sama dengan
     * pengecualian izin, lihat HasCompanyLabelOverride (instruksi user 2026-10-01). */
    public function leaveSubstituteEnabled(User $user): bool
    {
        return LeaveTypeCategoryEnum::usesLabelOverride($user->company_id);
    }

    /** Calon pengganti cuti: karyawan aktif lain di company & unit (org_structure jabatan) yang
     * sama dengan $user, PLUS bawahannya (langsung & tidak langsung) - atasan boleh memilih
     * bawahannya sebagai pengganti walau bawahan itu tercatat di sub-unit lain. */
    public function unitMates(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $orgId = $user->profile?->jobPosition?->org_structure_id;
        $subordinateIds = app(\App\Services\OrgHierarchyService::class)->subordinates($user)->pluck('id');

        return User::query()
            ->where('company_id', $user->company_id)
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereHas('profile.jobPosition', fn ($j) => $j->where('org_structure_id', $orgId ?? '__none__'))
                ->orWhereIn('id', $subordinateIds));
    }

    public function storeLeaveRequest(array $data, ?User $actingUser = null, bool $skipApproval = false, ?string $note = null, ?User $createdBy = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $createdBy ??= $actingUser;
        $userId = $actingUser->id;
        $type = 'leave';
        $status = $skipApproval ? 'approved' : 'in_review';

        $masterFlow = null;
        if (! $skipApproval) {
            $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
                companyId: $actingUser->company_id,
                userId: $userId,
                subModule: AttendanceApprovalBucketEnum::LEAVE->value
            );

            if (!$masterFlow) {
                throw new \Exception('No active approval flow found for attendance requests for this company.');
            }
        }

        $leaveType = LeaveTypeCategoryEnum::from($data['type_id']);

        // ponytail: enforcement blokir submit dimatikan sementara atas permintaan user — kuota
        // tetap dihitung & ditampilkan ke approver (getCutiTahunanPeriodeUsage), cuma tidak lagi
        // mencegah pemohon submit. Nyalakan lagi dengan uncomment baris di bawah kalau kuota mau
        // jadi hard block lagi.
        // $this->assertWithinCutiTahunanMonthlyLimit($actingUser, $leaveType, $data);

        // Cuti khusus (haid/melahirkan/keguguran) TETAP diblokir kalau lewat kuota — ini hak
        // yang dibatasi UU, bukan kuota kebijakan yang boleh dilanggar seperti Cuti Tahunan.
        $this->assertWithinSpecialLeaveQuota($actingUser, $leaveType, $data);

        // Cuti Tahunan TETAP diblokir kalau masa kerja minimal belum masuk jendela pengajuan
        // (instruksi user 2026-09-05) — beda dari kuota Cuti Tahunan di atas yang sengaja
        // dilonggarkan, ini justru harus keras (dropdown jenis cuti sudah disaring di form,
        // ini jaring pengaman server-side).
        $this->assertCutiTahunanEligibility($actingUser, $leaveType, $data);

        // Masa kerja minimal (CUTI_MIN_MASA_KERJA_BULAN) berlaku ke jenis cuti selain Tahunan
        // (Tahunan punya jendela sendiri yang boleh diisi 1 bulan lebih awal, lihat
        // assertCutiTahunanEligibility di atas) — dropdown form sudah menyaring jenis cuti
        // lain kalau belum terpenuhi (SubmitLeaveRequest::getLeaveTypesProperty), ini jaring
        // pengaman server-side.
        if ($leaveType !== LeaveTypeCategoryEnum::TAHUNAN) {
            $this->assertMinimumTenureMet($actingUser);
        }

        // H-30 cuma untuk Cuti Tahunan, dan cuma jalur self-service (pemohon = pembuat request) -
        // admin/HC yang membuat pengajuan atas nama karyawan lewat Filament (createdBy beda dari
        // actingUser) sengaja TIDAK kena, supaya tetap bisa input cuti retroaktif/darurat.
        if ($leaveType === LeaveTypeCategoryEnum::TAHUNAN && $createdBy->id === $actingUser->id) {
            $this->assertMinimumLeadTime($data['start_date']);
        }

        $this->assertKeteranganWajibDiisi($actingUser, $data['reason'] ?? null, 'Alasan/Keterangan cuti');

        // Pengganti cuti cuma ada di 2 company RS (lihat leaveSubstituteEnabled) - company lain diabaikan.
        if (! $this->leaveSubstituteEnabled($actingUser)) {
            $data['substitute_user_id'] = null;
        }

        if (! empty($data['substitute_user_id']) && ! $this->unitMates($actingUser)->whereKey($data['substitute_user_id'])->exists()) {
            throw new \Exception('Pengganti harus karyawan lain di unit yang sama.');
        }

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'created_by' => $createdBy->id,
            'type' => $type,
            'status' => $status,
        ]);

        $leaveData = [
            'user_id' => $userId,
            'request_id' => $request->id,
            'type_id' => $data['type_id'],
            'leave_type' => $leaveType->label(),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'total_days' => $this->countLeaveDays($actingUser, $leaveType, $data['start_date'], $data['end_date'] ?? null),
            'attachments_files' => $data['attachments'] ?? null,
            'substitute_user_id' => $data['substitute_user_id'] ?? null,
        ];

        $this->leaveReqRepository->storeLeaveRequest($leaveData);

        if ($skipApproval) {
            $this->attendanceReqRepository->updateAttendanceRequest($request, [
                'approved_by' => $createdBy->id,
                'approved_at' => now(),
            ]);

            $this->approvalHistoryRepository->create([
                'requestable_type' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
                'requestable_id' => $request->id,
                'action' => 'approved',
                'actor_id' => $createdBy->id,
                'actor_name' => $createdBy->name,
                'note' => $note,
            ]);

            // Dibuat admin langsung approved (toggle "need_approval" dimatikan) - tidak lewat
            // ApprovalFlowService::advanceStep(), jadi sync roster & attendance_records harus
            // dipanggil manual di sini juga, kalau tidak baris Libur/status Cuti tidak pernah
            // tercatat buat pengajuan yang dibuat lewat jalur ini.
            $freshRequest = $request->fresh(['leaveDetails', 'permissionDetails', 'user']);
            $this->syncApprovedAbsenceToRoster($freshRequest);
            $this->syncApprovedAbsenceToAttendanceRecords($freshRequest);

            return $this->leaveReqRepository->findAttendanceReqLeaveDetail($request->id);
        }

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            moduleId: $request->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: $actingUser->company_id,
            userId: $userId
        );

        // Send notification to first approver
        $firstApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
        if ($firstApprover) {
            $this->notificationService->sendLeaveRequestNotification(
                approver: $firstApprover,
                requestData: [
                    'request_id' => $request->id,
                    'leave_type' => $leaveType->label(),
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->leaveReqRepository->findAttendanceReqLeaveDetail($request->id);
    }

    /**
     * Pemakaian kuota Cuti Tahunan per BULAN KALENDER (AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN)
     * untuk 1 leave type pada bulan yang mengandung $referenceDate — dipakai bareng oleh
     * assertWithinCutiTahunanMonthlyLimit (validasi submit, saat ini dimatikan sementara) DAN
     * info kuota pemohon di halaman approval (§8). Null kalau leave type ini bukan kategori
     * Tahunan (aturan ini memang tidak berlaku). "used" cuma menghitung status APPROVED
     * (konsisten dengan getLeaveUserBalance) — pengajuan yang masih in_review/submitted BELUM
     * mengurangi kuota periode sampai benar-benar diputuskan final.
     *
     * Sengaja TIDAK pakai AttendancePeriodResolver (itu punya periode Izin sendiri) — Cuti
     * Tahunan cuma bulan kalender biasa, independen dari setting periode Izin (dikonfirmasi
     * user, lihat knowledge/markdown/rencana-izin-empat-kategori.md §9).
     *
     * @return array{max: int, used: int, available: ?int, period_start: Carbon, period_end: Carbon}|null
     */
    public function getCutiTahunanPeriodeUsage(User $user, string $leaveTypeId, ?string $referenceDate = null): ?array
    {
        return $this->quotaCalculator->cutiTahunanPeriode($user, $leaveTypeId, $referenceDate);
    }

    /**
     * Bulan kerja user (dihitung dari UserProfiles::joining_date) vs
     * AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN — null kalau tidak ada batasan
     * (setting 0/tidak diset, atau joining_date kosong).
     *
     * @return array{months_worked: int, min_months: int}|null
     */
    private function tenureStatus(User $user): ?array
    {
        $minMonths = (int) Setting::resolve(AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN->value, $user->company_id, default: 0);

        if ($minMonths <= 0) {
            return null;
        }

        $joiningDate = $user->profile?->joining_date;

        if (! $joiningDate) {
            return null;
        }

        return ['months_worked' => (int) Carbon::parse($joiningDate)->diffInMonths(Carbon::now()), 'min_months' => $minMonths];
    }

    /**
     * Setting AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN berlaku ke SEMUA jenis cuti —
     * dipakai SubmitLeaveRequest::getLeaveTypesProperty() buat sembunyikan semua jenis cuti
     * dari dropdown kalau belum terpenuhi, dan assertMinimumTenureMet() di bawah buat blokir
     * server-side. True kalau tidak ada batasan (setting 0/tidak diset, atau joining_date
     * kosong).
     */
    public function minimumTenureMet(User $user): bool
    {
        $status = $this->tenureStatus($user);

        return ! $status || $status['months_worked'] >= $status['min_months'];
    }

    /**
     * Warning buat approver (detail pengajuan) kalau masa kerja pemohon belum mencapai
     * minimal sekian bulan saat pengajuan ini dibuat. Pemohon sendiri sudah tidak bisa
     * mengajukan sama sekali kalau masa kerja belum cukup (lihat minimumTenureMet()) — warning
     * ini cuma relevan buat pengajuan lama/manual yang dibuat sebelum masa kerja cukup.
     */
    public function getMinimumTenureWarning(User $user): ?string
    {
        $status = $this->tenureStatus($user);

        if (! $status || $status['months_worked'] >= $status['min_months']) {
            return null;
        }

        return "Masa kerja {$user->name} baru {$status['months_worked']} bulan (minimal kebijakan perusahaan: {$status['min_months']} bulan) — pertimbangkan sebelum menyetujui.";
    }

    /**
     * Pesan buat pemohon (form pengajuan) kalau masa kerja belum mencapai minimal kebijakan
     * perusahaan — semua jenis cuti disembunyikan dari dropdown selama ini. Null kalau tidak
     * dibatasi atau sudah terpenuhi.
     */
    public function getMinimumTenureBlockNote(User $user): ?string
    {
        $status = $this->tenureStatus($user);

        if (! $status || $status['months_worked'] >= $status['min_months']) {
            return null;
        }

        return "Anda belum bisa mengajukan cuti — masa kerja baru {$status['months_worked']} bulan (minimal kebijakan perusahaan: {$status['min_months']} bulan).";
    }

    /** Jaring pengaman server-side buat minimumTenureMet() — lihat storeLeaveRequest(). */
    private function assertMinimumTenureMet(User $actingUser): void
    {
        if (! $this->minimumTenureMet($actingUser)) {
            throw new \Exception('Anda belum bisa mengajukan cuti — masa kerja belum mencapai minimal kebijakan perusahaan.');
        }
    }

    /** Jaring pengaman server-side buat minLeaveDate() — lihat storeLeaveRequest() (Cuti Tahunan
     *  self-service saja). */
    private function assertMinimumLeadTime(string $startDate): void
    {
        if (Carbon::parse($startDate)->startOfDay()->lt($this->minLeaveDate())) {
            throw new \Exception('Cuti Tahunan wajib diajukan minimal 30 hari sebelum tanggal mulai.');
        }
    }

    /**
     * AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB — company-scoped, default OFF (Keterangan
     * tetap opsional kecuali company yang bersangkutan mengaktifkannya lewat Pengaturan Absensi).
     * Dicek di service (bukan cuma rules() Livewire) karena mobile API
     * (AttendanceRequestController::storeLeaveRequest/storePermissionRequest) submit langsung
     * lewat sini, tidak lewat form portal.
     */
    private function assertKeteranganWajibDiisi(User $actingUser, ?string $value, string $label): void
    {
        $wajib = Setting::resolve(AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB->value, $actingUser->company_id, default: false);

        if ($wajib && trim((string) $value) === '') {
            throw new \Exception("{$label} wajib diisi.");
        }
    }

    /**
     * Tanggal karyawan ini pertama kali ELIGIBLE mengambil Cuti Tahunan — masa kerja minimal
     * (AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN) sudah terpenuhi sejak tanggal ini.
     * Null = tidak dibatasi sama sekali (setting 0/tidak diset, atau joining_date kosong).
     * Beda dari getMinimumTenureWarning() (generik semua jenis cuti, cuma warning) - dipakai
     * KHUSUS buat kategori TAHUNAN (instruksi user 2026-09-05), dan MEMBLOKIR submit lewat
     * assertCutiTahunanEligibility() di bawah, bukan cuma warning.
     */
    public function cutiTahunanEligibleDate(User $user): ?Carbon
    {
        $minMonths = (int) Setting::resolve(AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN->value, $user->company_id, default: 0);

        if ($minMonths <= 0) {
            return null;
        }

        $joiningDate = $user->profile?->joining_date;

        if (! $joiningDate) {
            return null;
        }

        return Carbon::parse($joiningDate)->addMonths($minMonths)->startOfDay();
    }

    /**
     * Jendela pengajuan Cuti Tahunan dibuka 1 BULAN sebelum tanggal eligible (instruksi user
     * 2026-09-05, contoh: masa kerja minimal 12 bulan, gabung 5 Mei -> eligible 5 Mei tahun
     * depan, form baru boleh diisi mulai 5 April tahun depan) - tapi tanggal MULAI cuti yang
     * dipilih tetap wajib >= tanggal eligible (submit boleh lebih awal, cutinya sendiri
     * tidak boleh). Dropdown jenis cuti di form (SubmitLeaveRequest::getLeaveTypesProperty())
     * sudah menyaring Cuti Tahunan sebelum jendela ini terbuka - assert ini jaring pengaman
     * server-side kalau ada yang tetap mengirim request langsung.
     */
    private function assertCutiTahunanEligibility(User $actingUser, ?LeaveTypeCategoryEnum $leaveType, array $data): void
    {
        if ($leaveType !== LeaveTypeCategoryEnum::TAHUNAN) {
            return;
        }

        $eligibleDate = $this->cutiTahunanEligibleDate($actingUser);

        if (! $eligibleDate) {
            return;
        }

        $windowOpen = $eligibleDate->copy()->subMonth();

        if (Carbon::now()->lt($windowOpen)) {
            throw new \Exception("Cuti Tahunan baru bisa diajukan mulai {$windowOpen->translatedFormat('d M Y')} (1 bulan sebelum masa kerja minimal terpenuhi pada {$eligibleDate->translatedFormat('d M Y')}).");
        }

        if (Carbon::parse($data['start_date'])->lt($eligibleDate)) {
            throw new \Exception("Tanggal mulai Cuti Tahunan minimal {$eligibleDate->translatedFormat('d M Y')} — masa kerja minimal baru terpenuhi saat itu.");
        }
    }

    /**
     * Setting AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN (module attendance,
     * company-scoped) — batas AKUMULASI hari Cuti Tahunan (jenis cuti berkategori
     * LeaveTypeCategoryEnum::TAHUNAN, lihat LeaveTypeCategoryEnum) yang boleh diajukan 1 user
     * dalam 1 bulan kalender, dihitung dari bulan tanggal mulai pengajuan baru. 0/tidak diset =
     * tidak dibatasi. Cuma berlaku untuk jenis cuti yang eksplisit ditandai kategori Tahunan —
     * jenis cuti lain (cuti besar, dst) tidak ikut kena batas ini.
     */
    private function assertWithinCutiTahunanMonthlyLimit(User $actingUser, ?LeaveTypeCategoryEnum $leaveType, array $data): void
    {
        if ($leaveType !== LeaveTypeCategoryEnum::TAHUNAN) {
            return;
        }

        $usage = $this->getCutiTahunanPeriodeUsage($actingUser, $leaveType->value, $data['start_date']);

        if (! $usage || $usage['max'] <= 0) {
            return;
        }

        $start = Carbon::parse($data['start_date']);
        $end = ! empty($data['end_date']) ? Carbon::parse($data['end_date']) : $start->copy();
        $newDays = $start->diffInDays($end) + 1;

        if ($usage['used'] + $newDays > $usage['max']) {
            throw new \Exception("Melebihi batas Cuti Tahunan periode ini (maksimal {$usage['max']} hari, sudah diajukan {$usage['used']} hari, pengajuan ini {$newDays} hari).");
        }
    }

    /**
     * Validasi kuota cuti khusus sesuai LeaveTypeCategoryEnum::usageWindow():
     * - 'monthly'     (HAID)              : Σ hari approved+in_review pada BULAN kalender tanggal
     *   mulai + pengajuan baru tidak boleh > quota_days (mis. 2 hari/bulan).
     * - 'per_request' (MELAHIRKAN/KEGUGURAN): total_days 1 pengajuan tidak boleh > quota_days;
     *   tidak ada akumulasi (hak per kejadian).
     * "used" ikut menghitung in_review supaya pengajuan ganda yang masih pending tidak bisa
     * menembus kuota sebelum diputuskan.
     */
    private function assertWithinSpecialLeaveQuota(User $actingUser, ?LeaveTypeCategoryEnum $leaveType, array $data, ?string $excludeRequestId = null): void
    {
        $category = $leaveType;

        if (! $category || ! $category->isCutiKhusus()) {
            return;
        }

        $quota = $category->quotaDays();

        if ($quota <= 0) {
            return;
        }

        $start = Carbon::parse($data['start_date']);
        $end = ! empty($data['end_date']) ? Carbon::parse($data['end_date']) : $start->copy();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        // Konsisten dengan total_days yang disimpan — countLeaveDays() pakai hari kalender
        // khusus MELAHIRKAN/KEGUGURAN (lihat docblock-nya), hari kerja jadwal untuk sisanya.
        $newDays = $this->countLeaveDays($actingUser, $leaveType, $start->toDateString(), $end->toDateString());

        if ($category->usageWindow() === 'per_request') {
            if ($newDays > $quota) {
                throw new \Exception("{$category->label()} maksimal {$quota} hari per pengajuan (pengajuan ini {$newDays} hari).");
            }

            return;
        }

        // 'monthly' — HAID
        $periodStart = $start->copy()->startOfMonth();
        $periodEnd = $start->copy()->endOfMonth();

        $used = (int) AttendanceRequestLeaveDetails::query()
            ->where('category', $leaveType->value)
            ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->when($excludeRequestId, fn ($q) => $q->where('request_id', '!=', $excludeRequestId))
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('user_id', $actingUser->id)
                ->where('type', 'leave')
                ->whereIn('status', ['approved', 'in_review']))
            ->sum('total_days');

        if ($used + $newDays > $quota) {
            throw new \Exception("Melebihi kuota {$category->label()} bulan ini (maksimal {$quota} hari, sudah {$used} hari, pengajuan ini {$newDays} hari).");
        }
    }

    /**
     * Approver merevisi tanggal pengajuan Cuti Khusus / Izin Khusus saat menyetujui (tanggal
     * aktual bisa bergeser dari yang diajukan). Hanya dua kategori itu — dipanggil dari
     * AttendanceRequestDetail sebelum advanceStep. total_days dihitung ulang lewat
     * countLeaveDays() — sama seperti saat submit (hari kalender khusus MELAHIRKAN/KEGUGURAN,
     * hari kerja jadwal untuk Cuti lain), Izin tetap hari kalender.
     * Untuk cuti khusus kuota divalidasi ulang, request ini sendiri dikecualikan dari "used".
     */
    public function reviseRequestDates(AttendanceRequests $record, string $startDate, string $endDate): void
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($end->lt($start)) {
            throw new \Exception('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        if ($record->type === 'leave') {
            $detail = $record->leaveDetails()->first();

            if (! $detail) {
                return;
            }

            $leaveType = LeaveTypeCategoryEnum::tryFrom($detail->category ?? '');

            $this->assertWithinSpecialLeaveQuota($record->user, $leaveType, [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ], excludeRequestId: $record->id);

            $detail->update([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $this->countLeaveDays($record->user, $leaveType, $start->toDateString(), $end->toDateString()),
            ]);

            return;
        }

        if ($record->type === 'permission') {
            $detail = $record->permissionDetails()->first();

            if (! $detail) {
                return;
            }

            $detail->update([
                'date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $start->diffInDays($end) + 1,
            ]);
        }
    }

    /**
     * total_days pengajuan CUTI: 1 tanggal = 1 hari; kalau lebih dari 1 tanggal, hitung HARI
     * KERJA saja — tanggal yang jatuh pada off_day jadwal user tidak ikut (ScheduleResolver).
     * Izin TIDAK pakai ini (tetap hari kalender). MELAHIRKAN/KEGUGURAN JUGA tidak pakai ini —
     * lihat countLeaveDays() (hari kalender, sesuai UU).
     */
    private function countWorkingDays(?User $user, ?string $start, ?string $end): int
    {
        if (! $start) {
            return 0;
        }

        if (! $end || $end === $start) {
            return 1;
        }

        if (! $user) {
            return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        }

        return $this->scheduleResolver->workingDaysBetween($user, $start, $end);
    }

    /**
     * total_days pengajuan CUTI, category-aware: MELAHIRKAN/KEGUGURAN (LeaveTypeCategoryEnum::
     * countsCalendarDays()) dihitung hari KALENDER (UU 13/2003 Ps.82 - hak 90/45 hari itu
     * rentang kalender kontinu, bukan hari masuk kerja); kategori lain tetap hari kerja lewat
     * countWorkingDays() seperti sebelumnya.
     */
    private function countLeaveDays(?User $user, ?LeaveTypeCategoryEnum $leaveType, ?string $start, ?string $end): int
    {
        $category = $leaveType;

        if ($category?->countsCalendarDays()) {
            if (! $start) {
                return 0;
            }

            return Carbon::parse($start)->diffInDays(Carbon::parse($end ?? $start)) + 1;
        }

        return $this->countWorkingDays($user, $start, $end);
    }

    /**
     * Kuota per kelompok Izin (§8 — Meninggalkan Jam Kerja & Tidak Masuk Seharian masing-masing
     * setting sendiri, lihat izinQuotaSettingFor()). Sakit & Khusus tidak ada limit sama
     * sekali, jenis izin lain (category null) juga tidak kena batas ini. Terima $category
     * langsung (bukan objek jenis izin) — kelompok 2/3 tidak punya pilihan jenis sama sekali,
     * category-nya sudah diresolve pemanggil sebelum sampai sini.
     */
    private function assertWithinIzinPeriodeLimit(User $actingUser, ?string $categoryValue, array $data): void
    {
        $category = $categoryValue ? PermissionTypeCategoryEnum::tryFrom($categoryValue) : null;

        if (! $category) {
            return;
        }

        $group = PermissionGroupEnum::fromCategory($category);
        $quotaSetting = $this->quotaCalculator->izinQuotaSettingFor($group);

        if (! $quotaSetting) {
            return;
        }

        $maxPerPeriode = (int) Setting::resolve($quotaSetting->value, $actingUser->company_id, default: 0);

        if ($maxPerPeriode <= 0) {
            return;
        }

        $categoryValues = array_map(fn ($c) => $c->value, $group->categories());
        $date = Carbon::parse($data['date']);
        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve($date->copy(), $actingUser->company_id);

        $existingCount = AttendanceRequestPermissionDetails::query()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereIn('category', $categoryValues)
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('user_id', $actingUser->id)
                ->where('type', 'permission')
                ->whereNotIn('status', ['rejected', 'cancelled', 'failed']))
            ->count();

        if ($existingCount + 1 > $maxPerPeriode) {
            throw new \Exception("Melebihi batas {$group->label()} periode ini (maksimal {$maxPerPeriode} hari, sudah diajukan {$existingCount} hari).");
        }
    }

    /**
     * @param bool $skipApproval Dibuat langsung oleh admin (bukan pengajuan mandiri) tanpa lewat
     *   approval flow — status langsung 'approved', $note WAJIB diisi (divalidasi pemanggil).
     *   Lihat knowledge/changes untuk konteks: admin butuh cara input Izin manual (misal data
     *   susulan/koreksi) tanpa memaksa lewat approval berjenjang.
     * @param User|null $createdBy Admin yang membuat, dicatat di riwayat & approved_by. Default
     *   $actingUser kalau tidak diisi (pengajuan mandiri biasa).
     */
    public function storePermissionRequest(array $data, ?User $actingUser = null, bool $skipApproval = false, ?string $note = null, ?User $createdBy = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $createdBy ??= $actingUser;
        $userId = $actingUser->id;
        $type = 'permission';
        $status = $skipApproval ? 'approved' : 'in_review';

        // type_id (key PermissionTypeEnum) cuma ada untuk kelompok yang punya pilihan jenis (Meninggalkan Jam Kerja &
        // Khusus, lihat PermissionGroupEnum::hasSubKategori()) - kelompok flat (Tidak Masuk
        // Seharian, Sakit) kirim `category` langsung tanpa lookup master sama sekali.
        // Diresolusi sebelum blok flow karena `category` menentukan ember alur persetujuan.
        $permissionType = !empty($data['type_id']) ? PermissionTypeEnum::from($data['type_id']) : null;
        $category = $permissionType?->category()->value ?? ($data['category'] ?? null);
        // Label 2 company override (RSU Syifa Medika Banjarbaru/Barabai) tersimpan APA ADANYA di
        // permission_type - instruksi user 2026-09-30. `category` di atas TIDAK terpengaruh sama
        // sekali (ditentukan dari identitas enum case, bukan teks label), jadi aman terhadap
        // payroll/approval yang membaca category, bukan permission_type.
        $permissionTypeName = $permissionType?->label($actingUser->company_id)
            ?? PermissionTypeEnum::firstForCategory($category)?->label($actingUser->company_id)
            ?? ($category ? PermissionTypeCategoryEnum::tryFrom($category)?->label() : null);

        $masterFlow = null;
        if (! $skipApproval) {
            $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
                companyId: $actingUser->company_id,
                userId: $userId,
                subModule: AttendanceApprovalBucketEnum::fromPermissionCategory($category)?->value
            );

            if (!$masterFlow) {
                throw new \Exception('No active approval flow found for attendance requests for this company.');
            }
        }

        // ponytail: enforcement blokir submit dimatikan sementara atas permintaan user — kuota
        // tetap dihitung & ditampilkan ke approver (getIzinPeriodeUsage), cuma tidak lagi
        // mencegah pemohon submit. Nyalakan lagi dengan uncomment baris di bawah kalau kuota mau
        // jadi hard block lagi.
        // $this->assertWithinIzinPeriodeLimit($actingUser, $category, $data);

        $this->assertKeteranganWajibDiisi($actingUser, $data['purpose'] ?? null, 'Keterangan izin');

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'created_by' => $createdBy->id,
            'type' => $type,
            'status' => $status,
        ]);

        $permissionData = [
            'request_id' => $request->id,
            'permission_type' => $permissionTypeName,
            'category' => $category,
            'date' => $data['date'],
            'end_date' => $data['end_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'location' => $data['location'] ?? null,
            'substitute_user_id' => $data['substitute_user_id'] ?? null,
            'substitute_user_name' => $data['substitute_user_name'] ?? null,
            'substitute_task' => $data['substitute_task'] ?? null,
            'attachments_files' => $data['attachments'] ?? null,
        ];

        $this->permissionReqRepository->storePermissionRequest($permissionData);

        if ($skipApproval) {
            // Dibuat langsung oleh admin, tidak lewat approval flow - tandai approved langsung
            // + catat 1 baris riwayat (snapshot_id null, lihat migration
            // make_snapshot_id_nullable_on_approval_histories_table) supaya tetap kelihatan di
            // tab "Riwayat Approval" kenapa & oleh siapa izin ini langsung disetujui.
            $this->attendanceReqRepository->updateAttendanceRequest($request, [
                'approved_by' => $createdBy->id,
                'approved_at' => now(),
            ]);

            $this->approvalHistoryRepository->create([
                'requestable_type' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
                'requestable_id' => $request->id,
                'action' => 'approved',
                'actor_id' => $createdBy->id,
                'actor_name' => $createdBy->name,
                'note' => $note,
            ]);

            // Dibuat admin langsung approved (toggle "need_approval" dimatikan) - tidak lewat
            // ApprovalFlowService::advanceStep(), jadi sync roster & attendance_records harus
            // dipanggil manual di sini juga.
            $freshRequest = $request->fresh(['leaveDetails', 'permissionDetails', 'user']);
            $this->syncApprovedAbsenceToRoster($freshRequest);
            $this->syncApprovedAbsenceToAttendanceRecords($freshRequest);

            return $this->permissionReqRepository->findAttendanceReqPermissionDetail($request->id);
        }

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
            moduleId: $request->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: $actingUser->company_id,
            userId: $userId
        );

        // Send notification to first approver
        $firstApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
        if ($firstApprover) {
            $this->notificationService->sendPermissionRequestNotification(
                approver: $firstApprover,
                requestData: [
                    'request_id' => $request->id,
                    'permission_type' => $permissionTypeName,
                    'date' => $data['date'],
                    // Izin Tidak Masuk/Sakit (hari penuh) tidak punya jam - null, bukan crash
                    // "Undefined array key" (ketauan dari test 2026-09-14).
                    'start_time' => $data['start_time'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                    'purpose' => $data['purpose'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->permissionReqRepository->findAttendanceReqPermissionDetail($request->id);
    }

    /**
     * Cuti (semua jenis) & Izin kategori Tidak Masuk Seharian/Sakit yang DISETUJUI otomatis
     * ditandai Libur di roster shift (tab "Detail Penjadwalan") - instruksi user 2026-09-14.
     * Kategori izin lain (Terlambat Masuk/Pulang Cepat/Meninggalkan Jam Kerja/Khusus) SENGAJA
     * tidak disentuh - itu bukan "hari penuh tidak masuk", shift hari itu tetap relevan buat
     * dikerjakan sebagian. Cuma berlaku buat karyawan berjadwal tipe Shift (Fixed/Flexible tidak
     * punya konsep roster harian begini) - dipanggil dari ApprovalFlowService::advanceStep()
     * setelah attendance_request ditandai approved.
     */
    public function syncApprovedAbsenceToRoster(AttendanceRequests $request): void
    {
        $range = match ($request->type) {
            'leave' => $this->leaveDateRange($request),
            'permission' => $this->fullDayPermissionDateRange($request),
            default => null,
        };

        if (! $range || ! $request->user) {
            return;
        }

        [$start, $end] = $range;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $this->markRosterDayOff($request->user, $date->toDateString(), $request);
        }
    }

    /**
     * Cuti (semua jenis) & Izin Tidak Masuk Seharian/Sakit/Khusus yang DISETUJUI otomatis
     * dicatat di attendance_records (status leave/sick/permit) - biar Rekap Presensi tidak perlu
     * cross-check manual ke attendance_requests buat tahu karyawan lagi cuti/izin (instruksi user
     * 2026-09-24). Kategori izin partial-day (Terlambat Masuk/Pulang Cepat/Meninggalkan Jam
     * Kerja) SENGAJA tidak disentuh - shift hari itu tetap relevan dikerjakan sebagian, bukan
     * "tidak masuk seharian". Berlaku untuk SEMUA tipe jadwal (beda dengan
     * syncApprovedAbsenceToRoster() yang cuma berlaku karyawan tipe Shift), karena
     * attendance_records dipakai lintas jadwal, roster shift tidak. Dipanggil dari
     * ApprovalFlowService::advanceStep() sejalan dengan syncApprovedAbsenceToRoster().
     */
    public function syncApprovedAbsenceToAttendanceRecords(AttendanceRequests $request): void
    {
        if (! $request->user) {
            return;
        }

        $status = match ($request->type) {
            'leave' => AttendanceStatus::LEAVE,
            'permission' => $this->fullDayPermissionStatus($request),
            default => null,
        };

        $range = match ($request->type) {
            'leave' => $this->leaveDateRange($request),
            'permission' => $this->fullDayAttendanceRecordPermissionDateRange($request),
            default => null,
        };

        if (! $status || ! $range) {
            return;
        }

        [$start, $end] = $range;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $this->markAttendanceRecordAbsence($request->user, $date->toDateString(), $status, $request->id);
        }
    }

    /** Status attendance_records buat kategori izin sehari penuh - Sakit dapat status sendiri
     *  (SICK), Tidak Masuk & Khusus sama-sama jadi PERMIT (AttendanceStatus tidak punya case
     *  terpisah buat "Khusus"). */
    private function fullDayPermissionStatus(AttendanceRequests $request): ?AttendanceStatus
    {
        $detail = $request->permissionDetails->first();

        return match ($detail?->category) {
            PermissionTypeCategoryEnum::IZIN_SAKIT->value => AttendanceStatus::SICK,
            PermissionTypeCategoryEnum::IZIN_SEHARIAN->value, PermissionTypeCategoryEnum::IZIN_KHUSUS->value => AttendanceStatus::PERMIT,
            default => null,
        };
    }

    /** @return array{0: Carbon, 1: Carbon}|null Sengaja terpisah dari fullDayPermissionDateRange()
     *  (dipakai roster) - roster memang tidak menyentuh Izin Khusus (instruksi 2026-09-14), tapi
     *  attendance_records tetap harus mencatatnya (instruksi 2026-09-24). */
    private function fullDayAttendanceRecordPermissionDateRange(AttendanceRequests $request): ?array
    {
        $detail = $request->permissionDetails->first();

        $fullDayCategories = [
            PermissionTypeCategoryEnum::IZIN_SEHARIAN->value,
            PermissionTypeCategoryEnum::IZIN_SAKIT->value,
            PermissionTypeCategoryEnum::IZIN_KHUSUS->value,
        ];

        if (! $detail || ! in_array($detail->category, $fullDayCategories, true) || ! $detail->date) {
            return null;
        }

        return [Carbon::parse($detail->date), Carbon::parse($detail->end_date ?? $detail->date)];
    }

    /** Timpa 1 tanggal attendance_records jadi status Cuti/Izin/Sakit. Baris yang SUDAH ada
     *  check-in (karyawan beneran presensi hari itu) dibiarkan - approval retroaktif tidak boleh
     *  menghapus jejak kehadiran asli. */
    private function markAttendanceRecordAbsence(User $user, string $date, AttendanceStatus $status, ?string $requestId = null): void
    {
        $existing = AttendanceRecords::where('user_id', $user->id)->whereDate('date', $date)->first();

        if ($existing?->check_in_time) {
            return;
        }

        if ($existing) {
            $existing->update(['status' => $status->value, 'source' => 'leave_approved', 'source_request_id' => $requestId]);

            return;
        }

        AttendanceRecords::create([
            'user_id' => $user->id,
            'nip' => $user->nip,
            'date' => $date,
            'status' => $status->value,
            'source' => 'leave_approved',
            'source_request_id' => $requestId,
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function leaveDateRange(AttendanceRequests $request): ?array
    {
        $detail = $request->leaveDetails->first();

        if (! $detail || ! $detail->start_date) {
            return null;
        }

        return [Carbon::parse($detail->start_date), Carbon::parse($detail->end_date ?? $detail->start_date)];
    }

    /** @return array{0: Carbon, 1: Carbon}|null null kalau bukan kategori Izin Tidak Masuk
     *   Seharian / Izin Sakit - kategori lain (terlambat masuk/pulang cepat/meninggalkan jam
     *   kerja/khusus) BUKAN hari penuh tidak masuk, shift hari itu tetap relevan, tidak menandai
     *   roster (instruksi user 2026-09-14). */
    private function fullDayPermissionDateRange(AttendanceRequests $request): ?array
    {
        $detail = $request->permissionDetails->first();

        $fullDayCategories = [
            PermissionTypeCategoryEnum::IZIN_SEHARIAN->value,
            PermissionTypeCategoryEnum::IZIN_SAKIT->value,
        ];

        if (! $detail || ! in_array($detail->category, $fullDayCategories, true) || ! $detail->date) {
            return null;
        }

        return [Carbon::parse($detail->date), Carbon::parse($detail->end_date ?? $detail->date)];
    }

    /** Tandai 1 tanggal Libur di roster - baris existing ditimpa jadi off_day=true (shift yang
     * sudah terjadwal hari itu batal, karyawannya cuti/izin), baris yang belum ada dibuatkan
     * baru. Baris yang SUDAH Libur dibiarkan (hindari log riwayat kosong/dobel). Dicatat ke
     * schedule_shift_roster_logs source='leave_approved' biar kelihatan asal-usulnya di
     * Riwayat Perubahan, bukan cuma "manual" tanpa konteks. `schedule_status` diisi 'cuti'/'izin'
     * (kolom sudah ada di tabel, sebelumnya tidak dipakai sama sekali) - dipakai
     * ScheduleShiftRosterTab buat menandai & MENGUNCI sel ini dari edit manual/Isi Cepat/Import/
     * Reset (instruksi user 2026-09-24: tidak boleh ketimpa balik jadi shift kerja biasa lewat
     * jalur bulk), dan buat kode "C"/"I" (beda dari "L" Libur biasa) di grid & export Excel.
     */
    private function markRosterDayOff(User $user, string $date, AttendanceRequests $request): void
    {
        $scheduleAssignment = ScheduleAssignment::resolveForUser($user, $date);

        if ($scheduleAssignment?->schedule?->schedule_type !== 'shift') {
            return;
        }

        $existing = ScheduleShiftAssignment::where('user_id', $user->id)->whereDate('date', $date)->first();

        if ($existing?->off_day) {
            return;
        }

        $before = $existing ? $existing->shift_type_id : null;
        $note = "Cuti atau Izin disetujui (pengajuan #{$request->id}).";
        $scheduleStatus = $request->type === 'leave' ? 'cuti' : 'izin';

        if ($existing) {
            $existing->update(['off_day' => true, 'shift_type_id' => null, 'notes' => $note, 'schedule_status' => $scheduleStatus, 'source_request_id' => $request->id]);
        } else {
            ScheduleShiftAssignment::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'date' => $date,
                'off_day' => true,
                'shift_type_id' => null,
                'notes' => $note,
                'schedule_status' => $scheduleStatus,
                'created_by' => $request->approved_by,
                'source_request_id' => $request->id,
            ]);
        }

        ScheduleShiftRosterLog::create([
            'schedule_id' => $scheduleAssignment->schedule_id,
            'user_id' => $user->id,
            'date' => $date,
            'old_value' => $before,
            'new_value' => 'off',
            'source' => 'leave_approved',
            'changed_by' => $request->approved_by,
        ]);
    }

    /** Tanggal "hari H" (mulai) cuti/izin ini sudah lewat - dipakai untuk menutup jendela
     *  pembatalan sendiri oleh pengaju (baik batal langsung saat in_review maupun ajukan
     *  pembatalan setelah approved). Null (tidak ada tanggal) dianggap sudah lewat - lebih aman
     *  menolak daripada membiarkan batal tanpa tanggal jelas. */
    public function cancellationCutoffPassed(AttendanceRequests $request): bool
    {
        $start = match ($request->type) {
            'leave' => $request->leaveDetails->first()?->start_date,
            'permission' => $request->permissionDetails->first()?->date,
            default => null,
        };

        if (! $start) {
            return true;
        }

        return now()->startOfDay()->gt(Carbon::parse($start)->startOfDay());
    }

    /** Kebalikan syncApprovedAbsenceToRoster()/syncApprovedAbsenceToAttendanceRecords() - dipanggil
     *  saat pengajuan yang sudah approved dibatalkan (lewat ApprovalFlowService::resolveCancellationRequest()).
     *  Aman dihapus langsung: cancellationCutoffPassed() menjamin tanggalnya >= hari ini, dan baris
     *  attendance_records yang sudah ada check-in asli tidak pernah disentuh sync (lihat
     *  markAttendanceRecordAbsence()), jadi tidak akan ikut terhapus di sini. */
    public function revertApprovedAbsenceSync(AttendanceRequests $request): void
    {
        ScheduleShiftAssignment::where('source_request_id', $request->id)->delete();
        AttendanceRecords::where('source_request_id', $request->id)->delete();
    }

    /**
     * Reschedule Cuti (instruksi user 2026-09-28): pengajuan LAMA otomatis DIBATALKAN LANGSUNG -
     * baik masih in_review maupun sudah approved sampai akhir - TANPA lewat alur approval
     * pembatalan (beda dari AttendanceCancellationService::requestCancellation() yang tetap
     * butuh approval kalau sudah approved). Kalau lama sudah approved, roster/attendance_records
     * yang tadi disinkron ikut di-revert (revertApprovedAbsenceSync) supaya tidak nyangkut ganda.
     * Setelah itu pengajuan BARU dibuat dengan tanggal baru lewat storeLeaveRequest() biasa -
     * approval dari awal lagi, kuota/eligibility dicek ulang normal (yang lama sudah cancelled
     * duluan jadi tidak ikut terhitung "used"). Efeknya sama seperti pemohon manual batal
     * lalu ajukan baru terpisah, cuma digabung jadi 1 aksi.
     *
     * Type-agnostic secara kode (semua jenis Cuti, bukan cuma Tahunan) - H-30 tetap berlaku
     * otomatis lewat storeLeaveRequest() karena reschedule ini SELALU self-service (actingUser
     * = createdBy = pemilik pengajuan lama).
     */
    public function rescheduleLeaveRequest(AttendanceRequests $record, string $newStart, string $newEnd, User $actingUser, string $reason): object
    {
        if ($record->type !== 'leave') {
            throw new \Exception('Jadwalkan ulang cuma berlaku untuk pengajuan Cuti.');
        }

        if ($record->user_id !== $actingUser->id) {
            throw new \Exception('Hanya pemilik pengajuan yang bisa menjadwalkan ulang.');
        }

        if (! in_array($record->status, ['in_review', 'approved'], true)) {
            throw new \Exception('Pengajuan ini sudah selesai diproses, tidak bisa dijadwalkan ulang.');
        }

        if ($this->cancellationCutoffPassed($record)) {
            throw new \Exception('Tanggal mulai pengajuan lama ini sudah lewat, tidak bisa dijadwalkan ulang.');
        }

        if ($record->cancellationRequests()->where('status', 'in_review')->exists()) {
            throw new \Exception('Sudah ada pengajuan pembatalan yang masih diproses untuk pengajuan ini, tunggu itu selesai dulu.');
        }

        if (Carbon::parse($newEnd)->lt(Carbon::parse($newStart))) {
            throw new \Exception('Tanggal selesai baru tidak boleh sebelum tanggal mulai baru.');
        }

        $category = $record->leaveDetails->first()?->category;

        if (! $category) {
            throw new \Exception('Jenis cuti pengajuan lama tidak ditemukan.');
        }

        return DB::transaction(function () use ($record, $newStart, $newEnd, $actingUser, $reason, $category) {
            $wasApproved = $record->status === 'approved';

            $this->attendanceReqRepository->updateAttendanceRequest($record, [
                'status' => AttendanceReqStatus::CANCELLED->value,
                'cancelled_by' => $actingUser->id,
                'cancelled_at' => now(),
            ]);

            $snapshot = $record->approvalSnapshot;
            if ($snapshot) {
                $snapshot->activity?->update(['status' => 'cancelled', 'acted_by' => $actingUser->id, 'acted_at' => now(), 'note' => $reason]);
                $snapshot->update(['status' => 'cancelled']);
            }

            $this->approvalHistoryRepository->create([
                'requestable_type' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value,
                'requestable_id' => $record->id,
                'snapshot_id' => $snapshot?->id,
                'activity_id' => $snapshot?->activity?->id,
                'action' => 'cancelled',
                'actor_id' => $actingUser->id,
                'actor_name' => $actingUser->name,
                'note' => "Dijadwalkan ulang oleh {$actingUser->name}: {$reason}",
            ]);

            if ($wasApproved) {
                $this->revertApprovedAbsenceSync($record);
            }

            return $this->storeLeaveRequest([
                'type_id' => $category,
                'start_date' => $newStart,
                'end_date' => $newEnd,
                'reason' => $reason,
            ], $actingUser);
        });
    }
}
