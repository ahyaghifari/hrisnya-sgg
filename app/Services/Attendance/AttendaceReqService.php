<?php

namespace App\Services\Attendance;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Approval\AttendanceApprovalBucketEnum;
use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Setting;
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

class AttendaceReqService
{
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

        $leaveType = $this->leaveReqRepository->getLeaveTypeById($data['type_id']);

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
            'leave_type' => $leaveType?->name,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'total_days' => $this->countLeaveDays($actingUser, $leaveType, $data['start_date'], $data['end_date'] ?? null),
            'attachments_files' => $data['attachments'] ?? null,
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
    private function assertCutiTahunanEligibility(User $actingUser, ?object $leaveType, array $data): void
    {
        if (! $leaveType || $leaveType->category !== LeaveTypeCategoryEnum::TAHUNAN->value) {
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
     * LeaveTypeCategoryEnum::TAHUNAN, lihat m_leave_types.category) yang boleh diajukan 1 user
     * dalam 1 bulan kalender, dihitung dari bulan tanggal mulai pengajuan baru. 0/tidak diset =
     * tidak dibatasi. Cuma berlaku untuk jenis cuti yang eksplisit ditandai kategori Tahunan —
     * jenis cuti lain (cuti besar, dst) tidak ikut kena batas ini.
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
     * Validasi kuota cuti khusus sesuai LeaveTypeCategoryEnum::usageWindow():
     * - 'monthly'     (HAID)              : Σ hari approved+in_review pada BULAN kalender tanggal
     *   mulai + pengajuan baru tidak boleh > quota_days (mis. 2 hari/bulan).
     * - 'per_request' (MELAHIRKAN/KEGUGURAN): total_days 1 pengajuan tidak boleh > quota_days;
     *   tidak ada akumulasi (hak per kejadian).
     * "used" ikut menghitung in_review supaya pengajuan ganda yang masih pending tidak bisa
     * menembus kuota sebelum diputuskan.
     */
    private function assertWithinSpecialLeaveQuota(User $actingUser, ?object $leaveType, array $data, ?string $excludeRequestId = null): void
    {
        $category = $leaveType ? LeaveTypeCategoryEnum::tryFrom($leaveType->category ?? '') : null;

        if (! $category || ! $category->isCutiKhusus()) {
            return;
        }

        $quota = (int) ($leaveType->quota_days ?? 0);

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
            ->where('leave_type_id', $leaveType->id)
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

            $leaveType = $this->leaveReqRepository->getLeaveTypeById($detail->leave_type_id);

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
    private function countLeaveDays(?User $user, ?object $leaveType, ?string $start, ?string $end): int
    {
        $category = $leaveType ? LeaveTypeCategoryEnum::tryFrom($leaveType->category ?? '') : null;

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
     * langsung (bukan objek permissionType) — kelompok 2/3 tidak lewat master sama sekali,
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

        // permission_type_id cuma ada untuk kelompok yang punya master (Meninggalkan Jam Kerja &
        // Khusus, lihat PermissionGroupEnum::hasSubKategori()) - kelompok flat (Tidak Masuk
        // Seharian, Sakit) kirim `category` langsung tanpa lookup master sama sekali.
        // Diresolusi sebelum blok flow karena `category` menentukan ember alur persetujuan.
        $permissionType = !empty($data['type_id']) ? $this->permissionReqRepository->getPermissionTypeById($data['type_id']) : null;
        $category = $permissionType?->category ?? ($data['category'] ?? null);
        $permissionTypeName = $permissionType?->name ?? ($category ? PermissionTypeCategoryEnum::tryFrom($category)?->label() : null);

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

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'created_by' => $createdBy->id,
            'type' => $type,
            'status' => $status,
        ]);

        $permissionData = [
            'request_id' => $request->id,
            'permission_type_id' => $data['type_id'] ?? null,
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
