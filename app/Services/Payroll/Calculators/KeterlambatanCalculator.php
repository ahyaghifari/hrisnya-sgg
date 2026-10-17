<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Employee\Employee;
use App\Services\Attendance\AttendancePeriodResolver;

/**
 * Setiap telat > threshold menit dipotong flat, TAPI dengan kompensasi (grace): selama jumlah
 * telat masih <= grace count, TIDAK ada potongan sama sekali. Begitu lolos grace, denda kena
 * ke SEMUA kejadian telat bulan itu (bukan cuma kelebihan di atas grace) - mis. grace=1: telat
 * 1x = bebas, telat 2x = 2x denda penuh, telat 3x = 3x denda penuh (dikoreksi 2026-08-12, beda
 * dari versi awal yang cuma mendenda kelebihannya). Dikonfirmasi user cuma berlaku Holding
 * (grace=1, di-seed PayrollSettingsSeeder), company lain default grace=0 (langsung kena denda
 * dari kejadian pertama, grace tidak berlaku).
 */
class KeterlambatanCalculator implements PayrollComponentCalculator
{
    public function calculate(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $denda = Setting::resolve('keterlambatan_denda', $period->company_id, default: 0);
        $graceCount = Setting::resolve('keterlambatan_grace_count', $period->company_id, default: 0);

        if ($denda <= 0) {
            return;
        }

        // Window absensi (21-20 default, dari Pengaturan Absensi) - BUKAN literal start_date/
        // end_date PayrollPeriod, sama fix-nya kayak kalkulator Izin (2026-08-02).
        [$attendancePeriodStart, $attendancePeriodEnd] = AttendancePeriodResolver::resolve($period->start_date, $period->company_id);

        // Tidak ada threshold menit terpisah lagi di sini (2026-08-12) - toleransi SUDAH
        // diterapkan sekali di level jadwal (FixedScheduleCalculator: status cuma jadi 'late'
        // kalau check-in lewat start_time+late_tolerance_minutes). Begitu status='late' (yang
        // berarti late_minutes pasti > 0/tidak null), itu sudah dianggap terlambat di sini -
        // tidak ada gerbang toleransi kedua yang menyaring ulang.
        $lateRecords = AttendanceRecords::query()
            ->where('user_id', $employee->user_id)
            ->whereBetween('date', [$attendancePeriodStart, $attendancePeriodEnd])
            ->where('status', 'late')
            ->whereNotNull('check_in_time')
            ->whereNotNull('late_minutes')
            ->where('late_minutes', '>', 0)
            ->orderBy('date')
            ->get();

        // Tanggal yang sudah ditutup Izin Terlambat Masuk (approved) tidak boleh kena denda
        // juga - izin itu justru komponen potongan sendiri (IzinMeninggalkanJamKerjaCalculator),
        // kena dua-duanya = double dip untuk kejadian telat yang sama.
        $excusedDates = AttendanceRequests::query()
            ->where('user_id', $employee->user_id)
            ->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', function ($q) use ($attendancePeriodStart, $attendancePeriodEnd) {
                $q->whereBetween('date', [$attendancePeriodStart, $attendancePeriodEnd])
                    ->where('category', PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value);
            })
            ->with(['permissionDetails' => fn ($q) => $q->where('category', PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value)])
            ->get()
            ->flatMap(fn ($request) => $request->permissionDetails->pluck('date'))
            ->map(fn ($date) => (string) $date)
            ->all();

        $lateRecords = $lateRecords->reject(fn ($record) => in_array((string) $record->date, $excusedDates, true));

        $lateCount = $lateRecords->count();

        if ($lateCount <= $graceCount) {
            return;
        }

        // Begitu lateCount lolos grace, denda kena ke SEMUA kejadian telat (bukan cuma
        // kelebihannya) - mis. grace=1: telat 1x = bebas, telat 2x = 2x denda penuh, telat
        // 3x = 3x denda penuh (2026-08-12, beda dari perilaku lama yang cuma mendenda
        // kelebihan di atas grace).
        $amount = $lateCount * (float) $denda;

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::KETERLAMBATAN->defaultRowName($period->company_id),
            'type' => PayrollComponentEnum::KETERLAMBATAN->type()->value,
            'quantity' => $lateCount,
            'unit' => 'kejadian',
            'unit_amount' => $denda,
            'amount' => $amount,
            'note' => "Total telat {$lateCount}x, kompensasi {$graceCount}x, {$lateCount}x/Bulan",
            'source_type' => PayrollComponentEnum::KETERLAMBATAN->value,
        ]);

        $context->addDeduction($amount);
    }
}
