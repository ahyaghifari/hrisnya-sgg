<?php

namespace App\Http\Controllers\Api\N8n;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\Holidays;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Endpoint rekap presensi untuk otomasi n8n. Scope dikunci ke divisi IT (holding) —
 * hardcode sesuai permintaan; ganti konstanta di bawah kalau butuh divisi lain.
 * Auth: HTTP Basic (config services.n8n.user / services.n8n.password).
 * Rate limit: 50 req/menit (throttle di routes/api.php).
 */
class DivisionAttendanceController extends Controller
{
    // ponytail: ULID di-hardcode (Syifa Global Group = holding, divisi "IT" level 2).
    // Kalau seed dijalankan ulang & id berubah, fallback lookup by name menutupi.
    private const HOLDING_COMPANY_ID = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';
    private const IT_DIVISION_ID = '01kekzd6r6fngjh69c7kk03bbb';

    /**
     * GET /api/n8n/offduty — cuti & izin divisi IT yang sedang berjalan / akan datang, dibatasi
     * sampai 1 bulan ke depan. Semua status kecuali batal & ditolak. Data: { cuti: [...], izin: [...] }.
     */
    public function offduty(Request $request)
    {
        if ($resp = $this->guard($request)) {
            return $resp;
        }

        $today = Carbon::today()->toDateString();
        $until = Carbon::today()->addMonthNoOverflow()->toDateString();
        $userIds = $this->divisionUserIds();

        $leave = AttendanceRequests::query()
            ->where('type', 'leave')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereIn('user_id', $userIds)
            ->whereHas('leaveDetails', fn ($q) => $q
                ->where('start_date', '<=', $until)
                ->where(fn ($q2) => $q2
                    ->where('end_date', '>=', $today)
                    ->orWhere(fn ($q3) => $q3->whereNull('end_date')->where('start_date', '>=', $today))
                )
            )
            ->with(['user:id,name,nip,email', 'leaveDetails'])
            ->get()
            ->flatMap(fn (AttendanceRequests $req) => $req->leaveDetails
                ->filter(fn ($d) => $d->start_date <= $until
                    && ($d->start_date >= $today || ($d->end_date && $d->end_date >= $today)))
                ->map(fn ($d) => [
                    'user' => $this->userPayload($req->user),
                    'status' => $req->status,
                    'leave_type' => $d->leave_type,
                    'start_date' => $this->dateStr($d->start_date),
                    'end_date' => $this->dateStr($d->end_date),
                    'total_days' => $d->total_days,
                    'reason' => $req->reason,
                ]))
            ->values();

        $permission = AttendanceRequests::query()
            ->where('type', 'permission')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereIn('user_id', $userIds)
            ->whereHas('permissionDetails', fn ($q) => $q
                ->where('date', '<=', $until)
                ->where(fn ($q2) => $q2
                    ->where('date', '>=', $today)
                    ->orWhere(fn ($q3) => $q3->whereNotNull('end_date')->where('end_date', '>=', $today))
                )
            )
            ->with(['user:id,name,nip,email', 'permissionDetails'])
            ->get()
            ->flatMap(fn (AttendanceRequests $req) => $req->permissionDetails
                ->filter(fn ($d) => $d->date <= $until
                    && ($d->date >= $today || ($d->end_date && $d->end_date >= $today)))
                ->map(fn ($d) => [
                    'user' => $this->userPayload($req->user),
                    'status' => $req->status,
                    'permission_type' => $d->permission_type,
                    'category' => $d->category,
                    'date' => $this->dateStr($d->date),
                    'end_date' => $this->dateStr($d->end_date),
                    'start_time' => $d->start_time,
                    'end_time' => $d->end_time,
                    'purpose' => $d->purpose,
                    'reason' => $req->reason,
                ]))
            ->values();

        return ApiResponseHelper::success(
            ['cuti' => $leave, 'izin' => $permission],
            'Rekap cuti & izin divisi IT (hari ini s.d. 1 bulan ke depan).'
        );
    }

    /**
     * GET /api/n8n/absen — status presensi hari ini per karyawan divisi IT.
     * `perlu_ditindak` = belum check-in DAN tidak ada alasan sah (cuti / izin selain
     * izin khusus / libur / jadwal off). Karyawan tanpa jadwal tidak ditandai.
     */
    public function absen(Request $request)
    {
        if ($resp = $this->guard($request)) {
            return $resp;
        }

        $today = Carbon::today()->toDateString();
        $holiday = Holidays::where('date', $today)->first();
        $resolver = app(ScheduleResolver::class);

        $users = User::query()
            ->whereIn('id', $this->divisionUserIds())
            ->with('profile:id,user_id')
            ->orderBy('name')
            ->get();

        $data = $users->map(function (User $user) use ($today, $holiday, $resolver) {
            $attendance = AttendanceRecords::where('user_id', $user->id)->where('date', $today)->first();
            $checkedIn = ! empty($attendance?->check_in_time);

            $resolved = $resolver->resolveForUserDate($user, $today);
            $noSchedule = $resolved === null;
            $scheduleOff = (bool) ($resolved['is_off_day'] ?? false);

            $leave = AttendanceRequests::query()
                ->where('user_id', $user->id)->where('type', 'leave')->where('status', 'approved')
                ->whereHas('leaveDetails', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNotNull('end_date')->where('start_date', '<=', $today)->where('end_date', '>=', $today))
                    ->orWhere(fn ($q2) => $q2->whereNull('end_date')->where('start_date', $today))
                )
                ->with('leaveDetails')->first();
            $leaveDetail = $leave?->leaveDetails->first();

            $permission = AttendanceRequests::query()
                ->where('user_id', $user->id)->where('type', 'permission')->where('status', 'approved')
                ->whereHas('permissionDetails', fn ($q) => $q
                    ->where('date', '<=', $today)
                    ->where(fn ($q2) => $q2->whereNull('end_date')->where('date', $today)->orWhere('end_date', '>=', $today))
                )
                ->with(['permissionDetails' => fn ($q) => $q
                    ->where('date', '<=', $today)
                    ->where(fn ($q2) => $q2->whereNull('end_date')->where('date', $today)->orWhere('end_date', '>=', $today))
                ])->first();
            $permissionDetail = $permission?->permissionDetails->first();
            $isIzinKhusus = $permissionDetail?->category === PermissionTypeCategoryEnum::IZIN_KHUSUS->value;

            $excused = $leaveDetail || ($permissionDetail && ! $isIzinKhusus) || $holiday || $scheduleOff;
            $perluDitindak = ! $checkedIn && ! $excused && ! $noSchedule;

            return [
                'user' => $this->userPayload($user),
                'checked_in' => $checkedIn,
                'check_in_time' => $attendance?->check_in_time,
                'check_out_time' => $attendance?->check_out_time,
                'on_leave' => (bool) $leaveDetail,
                'leave_type' => $leaveDetail?->leave_type,
                'on_permission' => (bool) $permissionDetail,
                'permission_type' => $permissionDetail?->permission_type,
                'permission_category' => $permissionDetail?->category,
                'is_holiday' => (bool) $holiday,
                'holiday_name' => $holiday?->name,
                'schedule_off' => $scheduleOff,
                'no_schedule' => $noSchedule,
                'perlu_ditindak' => $perluDitindak,
            ];
        })->values();

        return ApiResponseHelper::success([
            'date' => $today,
            'total' => $data->count(),
            'perlu_ditindak' => $data->where('perlu_ditindak', true)->pluck('user.name')->values(),
            'karyawan' => $data,
        ], 'Status presensi hari ini divisi IT.');
    }

    /** ULID karyawan aktif divisi IT (holding), lewat jabatan atau profil. */
    private function divisionUserIds(): array
    {
        $div = self::IT_DIVISION_ID;
        $match = fn ($oq) => $oq->where('id', $div)->orWhere('parent_id', $div); // divisi + sub-divisinya

        return User::query()
            ->where('is_active', true)
            ->where('company_id', self::HOLDING_COMPANY_ID)
            ->where(fn ($q) => $q
                ->whereHas('profile.jobPosition.orgStructure', $match)
                ->orWhereHas('profile.orgStructure', $match))
            ->pluck('id')
            ->all();
    }

    private function userPayload(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'nip' => $user->nip, 'email' => $user->email] : null;
    }

    private function dateStr($date): ?string
    {
        if (! $date) {
            return null;
        }

        return $date instanceof \DateTimeInterface ? Carbon::instance($date)->toDateString() : (string) $date;
    }

    /** Cek HTTP Basic auth. Return response 401 kalau gagal, null kalau lolos. */
    private function guard(Request $request)
    {
        $user = config('services.n8n.user');
        $pass = config('services.n8n.password');

        $ok = $user && $pass
            && hash_equals($user, (string) $request->getUser())
            && hash_equals($pass, (string) $request->getPassword());

        if (! $ok) {
            return response()->json(
                ['success' => false, 'message' => 'Unauthorized.', 'errors' => null],
                401,
                ['WWW-Authenticate' => 'Basic realm="n8n"'],
            );
        }

        return null;
    }
}
