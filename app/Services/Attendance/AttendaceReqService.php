<?php

namespace App\Services\Attendance;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Attendance\AttendanceReqRepository;
use App\Repositories\Attendance\LeaveReqRepository;
use App\Repositories\Attendance\OvertimeReqRepository;
use App\Repositories\Attendance\PermissionReqRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;

class AttendaceReqService
{
    public function __construct(
        protected AttendanceReqRepository $attendanceReqRepository,
        protected OvertimeReqRepository $overtimeReqRepository,
        protected LeaveReqRepository $leaveReqRepository,
        protected PermissionReqRepository $permissionReqRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected NotificationService $notificationService,
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

    public function getLeaveUserBalance(string $userId, string $typeId): array
    {
        return $this->leaveReqRepository->getLeaveUserBalance($userId, $typeId);
    }

    /**
     * Pemakaian kuota Izin gabungan (AttendanceSettingEnum::IZIN_MAX_PER_PERIODE) untuk 1 user
     * pada periode yang mengandung $referenceDate — dipakai buat menampilkan info kuota
     * pemohon di halaman approval (§8), bukan buat validasi (lihat assertWithinIzinPeriodeLimit
     * untuk itu). "used" cuma menghitung yang sudah APPROVED (konsisten dengan
     * getLeaveUserBalance) — beda dari assertWithinIzinPeriodeLimit yang juga menghitung
     * in_review supaya tidak over-commit sebelum di-approve.
     */
    public function getIzinPeriodeUsage(User $user, ?string $referenceDate = null): array
    {
        $categoryValues = array_map(fn ($c) => $c->value, PermissionTypeCategoryEnum::cases());
        $maxPerPeriode = (int) Setting::resolve(AttendanceSettingEnum::IZIN_MAX_PER_PERIODE->value, $user->company_id, default: 0);

        $anchor = $referenceDate ? Carbon::parse($referenceDate) : now();
        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve($anchor, $user->company_id);

        $used = AttendanceRequestPermissionDetails::query()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereHas('permissionType', fn ($q) => $q->whereIn('category', $categoryValues))
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('user_id', $user->id)
                ->where('type', 'permission')
                ->where('status', 'approved'))
            ->count();

        return [
            'max' => $maxPerPeriode,
            'used' => $used,
            'available' => $maxPerPeriode > 0 ? max(0, $maxPerPeriode - $used) : null,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    public function storeOvertimeRequest(array $data, ?User $actingUser = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $userId = $actingUser->id;
        $type = 'overtime';
        $status = 'in_review';

        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: 'attendance_request',
            companyId: $actingUser->company_id,
            userId: $userId
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for attendance requests for this company.');
        }

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
        ]);

        $overtimeType = $this->overtimeReqRepository->getOvertimeTypeById($data['type_id']);

        $uploadedFiles = $data['attachments'] ?? null;

        $overtimeData = [
            'request_id' => $request->id,
            'overtime_type_id' => $overtimeType?->id,
            'overtime_type' => $overtimeType?->name,
            'date' => $data['date'] ?? null,
            'tasks' => $data['tasks'] ?? null,
            'attachments_files' => $uploadedFiles,
            'task_description' => $data['task_description'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
        ];

        $this->overtimeReqRepository->storeOvertimeRequest($overtimeData);

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: 'attendance_request',
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
                    'overtime_type' => $overtimeType?->name,
                    'date' => $data['date'] ?? null,
                    'start_time' => $data['start_time'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->overtimeReqRepository->findAttendanceReqOvertimeDetail($request->id);
    }

    public function storeLeaveRequest(array $data, ?User $actingUser = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $userId = $actingUser->id;
        $type = 'leave';
        $status = 'in_review';

        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: 'attendance_request',
            companyId: $actingUser->company_id,
            userId: $userId
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for attendance requests for this company.');
        }

        $leaveType = $this->leaveReqRepository->getLeaveTypeById($data['type_id']);

        // ponytail: enforcement blokir submit dimatikan sementara atas permintaan user — kuota
        // tetap dihitung & ditampilkan ke approver (getCutiTahunanPeriodeUsage), cuma tidak lagi
        // mencegah pemohon submit. Nyalakan lagi dengan uncomment baris di bawah kalau kuota mau
        // jadi hard block lagi.
        // $this->assertWithinCutiTahunanMonthlyLimit($actingUser, $leaveType, $data);

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
        ]);

        $leaveData = [
            'user_id' => $userId,
            'request_id' => $request->id,
            'type_id' => $data['type_id'],
            'leave_type' => $leaveType?->name,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
        ];

        $this->leaveReqRepository->storeLeaveRequest($leaveData);

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: 'attendance_request',
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
                    'leave_type' => $leaveType?->name,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->leaveReqRepository->findAttendanceReqLeaveDetail($request->id);
    }

    /**
     * Pemakaian kuota Cuti Tahunan per-periode (AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN)
     * untuk 1 leave type pada periode yang mengandung $referenceDate — dipakai bareng oleh
     * assertWithinCutiTahunanMonthlyLimit (validasi submit, saat ini dimatikan sementara) DAN
     * info kuota pemohon di halaman approval (§8). Null kalau leave type ini bukan kategori
     * Tahunan (aturan ini memang tidak berlaku). "used" cuma menghitung status APPROVED
     * (konsisten dengan getLeaveUserBalance) — pengajuan yang masih in_review/submitted BELUM
     * mengurangi kuota periode sampai benar-benar diputuskan final.
     *
     * @return array{max: int, used: int, available: ?int, period_start: Carbon, period_end: Carbon}|null
     */
    public function getCutiTahunanPeriodeUsage(User $user, string $leaveTypeId, ?string $referenceDate = null): ?array
    {
        $leaveType = $this->leaveReqRepository->getLeaveTypeById($leaveTypeId);

        if (! $leaveType || $leaveType->category !== LeaveTypeCategoryEnum::TAHUNAN->value) {
            return null;
        }

        $maxPerBulan = (int) Setting::resolve(AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN->value, $user->company_id, default: 0);

        $anchor = $referenceDate ? Carbon::parse($referenceDate) : now();
        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve($anchor, $user->company_id);

        $used = (int) AttendanceRequestLeaveDetails::query()
            ->where('leave_type_id', $leaveTypeId)
            ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('user_id', $user->id)
                ->where('type', 'leave')
                ->where('status', 'approved'))
            ->sum('total_days');

        return [
            'max' => $maxPerBulan,
            'used' => $used,
            'available' => $maxPerBulan > 0 ? max(0, $maxPerBulan - $used) : null,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    /**
     * Setting AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN (module attendance,
     * company-scoped) — TIDAK memblokir submit (sengaja, sama seperti kuota Cuti
     * Tahunan/Izin di atas), cuma dipakai buat tampilkan warning ke pemohon (form pengajuan)
     * dan approver (detail pengajuan) kalau masa kerja user (dihitung dari
     * UserProfiles::joining_date) belum mencapai minimal sekian bulan. 0/tidak diset/
     * joining_date kosong = tidak ada warning.
     */
    public function getMinimumTenureWarning(User $user): ?string
    {
        $minMonths = (int) Setting::resolve(AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN->value, $user->company_id, default: 0);

        if ($minMonths <= 0) {
            return null;
        }

        $joiningDate = $user->profile?->joining_date;

        if (! $joiningDate) {
            return null;
        }

        $monthsWorked = (int) Carbon::parse($joiningDate)->diffInMonths(Carbon::now());

        if ($monthsWorked >= $minMonths) {
            return null;
        }

        return "Masa kerja {$user->name} baru {$monthsWorked} bulan (minimal kebijakan perusahaan: {$minMonths} bulan) — pertimbangkan sebelum menyetujui.";
    }

    /**
     * Setting AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN (module attendance,
     * company-scoped) — batas AKUMULASI hari Cuti Tahunan (jenis cuti berkategori
     * LeaveTypeCategoryEnum::TAHUNAN, lihat m_leave_types.category) yang boleh diajukan 1 user
     * dalam 1 periode (lihat AttendancePeriodResolver — ikut cutoff company, default kalender
     * 1-31), dihitung dari periode tanggal mulai pengajuan baru. 0/tidak diset = tidak dibatasi.
     * Cuma berlaku untuk jenis cuti yang eksplisit ditandai kategori Tahunan — jenis cuti
     * lain (cuti besar, dst) tidak ikut kena batas ini.
     */
    private function assertWithinCutiTahunanMonthlyLimit(User $actingUser, ?object $leaveType, array $data): void
    {
        if (! $leaveType || $leaveType->category !== LeaveTypeCategoryEnum::TAHUNAN->value) {
            return;
        }

        $usage = $this->getCutiTahunanPeriodeUsage($actingUser, $leaveType->id, $data['start_date']);

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
     * Setting AttendanceSettingEnum::IZIN_MAX_PER_PERIODE (module attendance, company-scoped)
     * — batas jumlah pengajuan Izin (semua kategori PermissionTypeCategoryEnum digabung jadi
     * 1 kuota — Terlambat Masuk, Pulang Cepat, Meninggalkan Jam Kerja, Izin Seharian, dikonfirmasi
     * user) dalam 1 periode. Cuma berlaku untuk jenis izin yang eksplisit ditandai salah satu
     * kategori itu (m_permission_types.category) — jenis izin lain tidak ikut kena batas ini.
     */
    private function assertWithinIzinPeriodeLimit(User $actingUser, ?object $permissionType, array $data): void
    {
        $categoryValues = array_map(fn ($c) => $c->value, PermissionTypeCategoryEnum::cases());

        if (! $permissionType || ! in_array($permissionType->category, $categoryValues, true)) {
            return;
        }

        $maxPerPeriode = (int) Setting::resolve(AttendanceSettingEnum::IZIN_MAX_PER_PERIODE->value, $actingUser->company_id, default: 0);

        if ($maxPerPeriode <= 0) {
            return;
        }

        $date = Carbon::parse($data['date']);
        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve($date->copy(), $actingUser->company_id);

        $existingCount = AttendanceRequestPermissionDetails::query()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereHas('permissionType', fn ($q) => $q->whereIn('category', $categoryValues))
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('user_id', $actingUser->id)
                ->where('type', 'permission')
                ->whereNotIn('status', ['rejected', 'cancelled', 'failed']))
            ->count();

        if ($existingCount + 1 > $maxPerPeriode) {
            throw new \Exception("Melebihi batas Izin periode ini (maksimal {$maxPerPeriode} hari, sudah diajukan {$existingCount} hari).");
        }
    }

    public function storePermissionRequest(array $data, ?User $actingUser = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $userId = $actingUser->id;
        $type = 'permission';
        $status = 'in_review';

        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: 'attendance_request',
            companyId: $actingUser->company_id,
            userId: $userId
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for attendance requests for this company.');
        }

        $permissionType = $this->permissionReqRepository->getPermissionTypeById($data['type_id']);

        // ponytail: enforcement blokir submit dimatikan sementara atas permintaan user — kuota
        // tetap dihitung & ditampilkan ke approver (getIzinPeriodeUsage), cuma tidak lagi
        // mencegah pemohon submit. Nyalakan lagi dengan uncomment baris di bawah kalau kuota mau
        // jadi hard block lagi.
        // $this->assertWithinIzinPeriodeLimit($actingUser, $permissionType, $data);

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
        ]);

        $permissionData = [
            'request_id' => $request->id,
            'permission_type_id' => $data['type_id'],
            'permission_type' => $permissionType?->name,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'purpose' => $data['purpose'] ?? null,
            'location' => $data['location'] ?? null,
            'substitute_user_id' => $data['substitute_user_id'] ?? null,
            'substitute_user_name' => $data['substitute_user_name'] ?? null,
            'substitute_task' => $data['substitute_task'] ?? null,
        ];

        $this->permissionReqRepository->storePermissionRequest($permissionData);

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: 'attendance_request',
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
                    'permission_type' => $permissionType?->name,
                    'date' => $data['date'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'purpose' => $data['purpose'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->permissionReqRepository->findAttendanceReqPermissionDetail($request->id);
    }
}
