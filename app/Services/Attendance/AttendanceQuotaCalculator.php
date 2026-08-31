<?php

namespace App\Services\Attendance;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\Attendance\PermissionGroupEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Attendance\LeaveReqRepository;
use Carbon\Carbon;

/**
 * Satu tempat untuk BACA sisa kesempatan / kuota pengajuan (Cuti Tahunan, Cuti Khusus, Izin) —
 * dipakai bareng oleh form pengajuan, kartu saldo, halaman approval, dan API. Refactor murni
 * dari AttendaceReqService (perilaku angka identik).
 *
 * TIDAK memblokir apa pun — enforcement tetap di AttendaceReqService::assertWithin*().
 * Konvensi "used": semua method di sini cuma menghitung pengajuan berstatus APPROVED
 * (pengajuan in_review belum mengurangi kuota tampilan). Enforcement yang butuh hitung
 * in_review melakukannya sendiri.
 */
class AttendanceQuotaCalculator
{
    public function __construct(
        protected LeaveReqRepository $leaveReqRepository,
    ) {}

    /**
     * Saldo cuti untuk 1 (user, jenis cuti). Window: Cuti Haid per BULAN kalender
     * (LeaveTypeCategoryEnum::usageWindow 'monthly'), sisanya per TAHUN kalender.
     * Cuti bersama (Holidays.is_collective_leave) hanya mengurangi Cuti Tahunan.
     *
     * @return array{quota:int, used:int, collective_leave_days:int, available:int}
     */
    public function leaveBalance(string $userId, string $typeId): array
    {
        return $this->leaveReqRepository->getLeaveUserBalance($userId, $typeId);
    }

    /**
     * Pemakaian kuota Cuti Tahunan per BULAN KALENDER (setting CUTI_TAHUNAN_MAX_PER_BULAN) untuk
     * 1 leave type pada bulan yang mengandung $referenceDate. Null kalau leave type bukan
     * kategori TAHUNAN. Sengaja TIDAK pakai AttendancePeriodResolver — Cuti Tahunan pakai bulan
     * kalender biasa, independen dari periode Izin.
     *
     * @return array{max:int, used:int, available:?int, period_start:Carbon, period_end:Carbon}|null
     */
    public function cutiTahunanPeriode(User $user, string $leaveTypeId, ?string $referenceDate = null): ?array
    {
        $leaveType = $this->leaveReqRepository->getLeaveTypeById($leaveTypeId);

        if (! $leaveType || $leaveType->category !== LeaveTypeCategoryEnum::TAHUNAN->value) {
            return null;
        }

        $maxPerBulan = (int) Setting::resolve(AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN->value, $user->company_id, default: 0);

        $anchor = $referenceDate ? Carbon::parse($referenceDate) : now();
        $periodStart = $anchor->copy()->startOfMonth();
        $periodEnd = $anchor->copy()->endOfMonth();

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
     * Setting AttendanceSettingEnum yang jadi kuota per periode untuk 1 kelompok Izin. Null =
     * kelompok itu tidak ada limit sama sekali (Sakit & Khusus).
     */
    public function izinQuotaSettingFor(PermissionGroupEnum $group): ?AttendanceSettingEnum
    {
        return match ($group) {
            PermissionGroupEnum::MENINGGALKAN_JAM_KERJA => AttendanceSettingEnum::IZIN_MAX_PER_PERIODE_JAM_KERJA,
            PermissionGroupEnum::TIDAK_MASUK_SEHARIAN => AttendanceSettingEnum::IZIN_MAX_PER_PERIODE_SEHARIAN,
            PermissionGroupEnum::SAKIT, PermissionGroupEnum::KHUSUS => null,
        };
    }

    /**
     * Pemakaian kuota Izin per kelompok untuk 1 user pada periode (AttendancePeriodResolver)
     * yang mengandung $referenceDate. Null kalau kelompok ini memang tidak ada limit.
     *
     * @return array{max:int, used:int, available:?int, period_start:Carbon, period_end:Carbon}|null
     */
    public function izinPeriode(User $user, PermissionGroupEnum $group, ?string $referenceDate = null): ?array
    {
        $quotaSetting = $this->izinQuotaSettingFor($group);

        if (! $quotaSetting) {
            return null;
        }

        $categoryValues = array_map(fn ($c) => $c->value, $group->categories());
        $maxPerPeriode = (int) Setting::resolve($quotaSetting->value, $user->company_id, default: 0);

        $anchor = $referenceDate ? Carbon::parse($referenceDate) : now();
        [$periodStart, $periodEnd] = AttendancePeriodResolver::resolve($anchor, $user->company_id);

        $used = AttendanceRequestPermissionDetails::query()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereIn('category', $categoryValues)
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
}
