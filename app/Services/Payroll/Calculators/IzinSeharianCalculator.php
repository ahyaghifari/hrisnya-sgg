<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Rumus dikonfirmasi user dari Excel (analisis-payroll-4 §7B.3):
 *   rate_harian = T8 / DAY(EOMONTH(periode, 0))
 *   potongan    = rate_harian * jumlah_kejadian
 * T8 = total pendapatan seluruh komponen — HARUS jalan setelah semua komponen EARNING (+
 * Fee Claim) terkumpul di PayrollContext, lihat urutan orchestrator di PayrollService.
 */
class IzinSeharianCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $count = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date])
                    ->where('category', PermissionTypeCategoryEnum::IZIN_SEHARIAN->value);
            })
            ->count();

        if ($count <= 0) {
            return;
        }

        $hariDalamBulan = $period->end_date->daysInMonth;

        if ($hariDalamBulan <= 0 || $context->grossSalary <= 0) {
            return;
        }

        $rateHarian = $context->grossSalary / $hariDalamBulan;
        $amount = round($rateHarian * $count, 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::IZIN_SEHARIAN->label(),
            'type' => PayrollComponentEnum::IZIN_SEHARIAN->type()->value,
            'quantity' => $count,
            'unit' => 'hari',
            'unit_amount' => round($rateHarian, 2),
            'amount' => $amount,
            'source_type' => PayrollComponentEnum::IZIN_SEHARIAN->value,
        ]);

        $context->addDeduction($amount);
    }
}
