<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\JobPositions;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Shift\ExtraHourAllowanceRules;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Logic sama persis PayrollService::handleExtraHourAllowanceComponent() lama — cuma sumber
 * batas jam kerja pindah dari AttendancePolicies ke Setting::resolve() (settings, generalisasi
 * Fase 0).
 */
class KelebihanJamKerjaCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $totalWorkingMinutes = (float) AttendanceRecords::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->whereNotNull('working_minutes')
            ->sum('working_minutes');

        $totalWorkingHours = round($totalWorkingMinutes / 60, 2);

        $minWorkingHours = Setting::resolve('min_total_working_hours_per_month', $period->company_id, default: 0);

        $excessHours = ceil($totalWorkingHours - $minWorkingHours);
        Log::info("KelebihanJamKerjaCalculator: User {$user->id} - Total Working Hours: {$totalWorkingHours}, Minimum Required: {$minWorkingHours}, Excess Hours: {$excessHours}");

        if ($excessHours <= 0) {
            return;
        }

        $jobPositionId = $user->activeJobPosition?->job_position_id;
        $allowanceRule = null;

        if ($jobPositionId) {
            $allowanceRule = ExtraHourAllowanceRules::query()
                ->where('company_id', $period->company_id)
                ->where('allowanceable_type', JobPositions::class)
                ->where('allowanceable_id', $jobPositionId)
                ->first();
        }

        if (! $allowanceRule) {
            $allowanceRule = ExtraHourAllowanceRules::query()
                ->where('company_id', $period->company_id)
                ->first();
        }

        $amountPerHour = $allowanceRule ? (float) $allowanceRule->amount_per_hour : 0;

        if ($amountPerHour <= 0) {
            return;
        }

        $amount = round($excessHours * $amountPerHour, 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::KELEBIHAN_JAM_KERJA->label(),
            'type' => PayrollComponentEnum::KELEBIHAN_JAM_KERJA->type()->value,
            'quantity' => $excessHours,
            'unit' => 'jam',
            'unit_amount' => $amountPerHour,
            'amount' => $amount,
            'note' => sprintf(
                'Total jam: %.2f, Minimum: %.2f, Kelebihan: %.2f jam × Rp%s/jam',
                $totalWorkingHours,
                $minWorkingHours,
                $excessHours,
                number_format($amountPerHour, 0, ',', '.')
            ),
            'source_type' => PayrollComponentEnum::KELEBIHAN_JAM_KERJA->value,
        ]);

        $context->addEarning($amount);
    }
}
