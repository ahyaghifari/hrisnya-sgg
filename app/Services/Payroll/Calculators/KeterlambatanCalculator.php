<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodResolver;

/**
 * Setiap telat > threshold menit dipotong flat, TAPI dengan kompensasi (grace): N kejadian
 * pertama dalam periode tidak kena denda, baru kejadian ke-(N+1) dst dipotong. Ini FITUR BARU
 * dibanding handleLateArrivalComponent() lama (yang selalu potong dari kejadian pertama) —
 * dikonfirmasi user cuma berlaku Holding (grace=1, di-seed PayrollSettingsSeeder), company
 * lain default grace=0 (perilaku sama seperti sebelumnya).
 */
class KeterlambatanCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $thresholdMinutes = Setting::resolve('keterlambatan_threshold_menit', $period->company_id, default: 0);
        $denda = Setting::resolve('keterlambatan_denda', $period->company_id, default: 0);
        $graceCount = Setting::resolve('keterlambatan_grace_count', $period->company_id, default: 0);

        if ($denda <= 0) {
            return;
        }

        // Window absensi (21-20 default, dari Pengaturan Absensi) - BUKAN literal start_date/
        // end_date PayrollPeriod, sama fix-nya kayak kalkulator Izin (2026-08-02).
        [$attendancePeriodStart, $attendancePeriodEnd] = AttendancePeriodResolver::resolve($period->start_date, $period->company_id);

        $lateRecords = AttendanceRecords::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$attendancePeriodStart, $attendancePeriodEnd])
            ->where('status', 'late')
            ->whereNotNull('check_in_time')
            ->when($thresholdMinutes > 0, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('late_minutes')->orWhere('late_minutes', '>', $thresholdMinutes)))
            ->orderBy('date')
            ->get();

        $lateCount = $lateRecords->count();

        if ($lateCount <= $graceCount) {
            return;
        }

        $chargeableCount = $lateCount - $graceCount;
        $amount = $chargeableCount * (float) $denda;

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::KETERLAMBATAN->defaultRowName($period->company_id),
            'type' => PayrollComponentEnum::KETERLAMBATAN->type()->value,
            'quantity' => $chargeableCount,
            'unit' => 'kejadian',
            'unit_amount' => $denda,
            'amount' => $amount,
            'note' => "Total telat {$lateCount}x, kompensasi {$graceCount}x, kena denda {$chargeableCount}x, {$chargeableCount}x/Bulan",
            'source_type' => PayrollComponentEnum::KETERLAMBATAN->value,
        ]);

        $context->addDeduction($amount);
    }
}
