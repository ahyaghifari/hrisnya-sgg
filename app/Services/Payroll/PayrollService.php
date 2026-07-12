<?php

namespace App\Services\Payroll;

use App\Enum\AttendanceReqStatus;
use App\Enum\Salary\CalculationTypeEnum;
use App\Enum\Salary\PayrollItemSourceEnum;
use App\Enum\Salary\SalaryComponentCategoryEnum;
use App\Models\Attendance\AttendancePolicies;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use App\Models\Master\JobEvaluationCompanies;
use App\Models\Master\JobPositions;
use App\Models\Payroll\FeeItem;
use App\Models\Payroll\SalaryComponentAssignments;
use App\Models\SalaryComponent\SalaryComponents;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Shift\ExtraHourAllowanceRules;
use App\Models\Shift\ShiftOvertimeCosts;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollService
{
    public function download(Payroll $payroll): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.payroll.payroll', [
            'payroll' => $payroll,
        ]);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->stream();
            },
            'slip-gaji-' . str_replace(' ', '-', strtolower($payroll->user->name)) . '.pdf',
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    public function regeneratePayroll(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            // Delete only unpaid payrolls; paid payrolls are kept as-is.
            // payroll_items will be removed automatically via cascadeOnDelete.
            Payroll::query()
                ->where('payroll_period_id', $period->id)
                ->where('is_paid', false)
                ->delete();

            // Re-run generation — payrollAlreadyExists() will skip paid ones.
            $this->generatePayroll($period);
        });
    }

    public function generatePayroll(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            $users = User::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get();

            foreach ($users as $user) {
                if ($this->payrollAlreadyExists($user, $period)) {
                    continue;
                }

                $payroll = Payroll::create([
                    'user_id' => $user->id,
                    'payroll_period_id' => $period->id,
                ]);

                $grossSalary = 0.0;
                $totalDeductions = 0.0;

                // 1) Base salary (by pangkat)
                $baseSalary = $this->handleBaseSalary($user, $payroll, $grossSalary);

                // 2) Job position fixed components
                $this->handleJobPositionFixedComponents($user, $payroll, $grossSalary, $totalDeductions);

                // 3) Job position percentage components (based on base salary)
                $this->handleJobPositionPercentageComponents($user, $payroll, $baseSalary, $grossSalary, $totalDeductions);

                // 4) Attendance-based components
                $this->handleLeaveComponent($user, $payroll, $period, $grossSalary, $totalDeductions);
                $this->handlePermissionComponent($user, $payroll, $period, $grossSalary, $totalDeductions);
                $this->handleOvertimeComponent($user, $payroll, $period, $baseSalary, $grossSalary, $totalDeductions);
                $this->handleLateArrivalComponent($user, $payroll, $period, $grossSalary, $totalDeductions);
                $this->handleExtraHourAllowanceComponent($user, $payroll, $period, $grossSalary);

                // 5) Incentive component
                $this->handleIncentiveComponent(
                    $user,
                    $payroll,
                    $period,
                    $grossSalary
                );

                // 6) Service fee (Jasa Pelayanan)
                $this->handleServiceFeeComponent(
                    $user,
                    $payroll,
                    $period,
                    $grossSalary
                );

                // 7) Fee claim (jasa dokter, dst — klaim per kejadian)
                $this->handleFeeItemsComponent(
                    $user,
                    $payroll,
                    $period,
                    $grossSalary,
                    $totalDeductions
                );

                $payroll->update([
                    'gross_salary' => $grossSalary,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $grossSalary - $totalDeductions,
                ]);
            }

            $period->update([
                'processed_at' => now(),
            ]);
        });
    }

    private function payrollAlreadyExists(User $user, PayrollPeriod $period): bool
    {
        return Payroll::query()
            ->where('user_id', $user->id)
            ->where('payroll_period_id', $period->id)
            ->exists();
    }

    /**
     * Returns base salary amount (float).
     */
    private function handleBaseSalary(User $user, Payroll $payroll, float &$grossSalary): float
    {
        $baseSalary = 0.0;

        // Cari eksplisit komponen "Gaji Pokok", bukan ->first() buta — pangkat secara teori
        // bisa saja di-assign komponen lain juga, jangan salah ambil (fix 2026-07-12).
        $baseSalaryComponent = $user->profile?->pangkat?->salaryComponents()
            ->whereHas('salaryComponent', fn ($q) => $q->where('category', SalaryComponentCategoryEnum::GAJI_POKOK->value))
            ->first();

        if (! $baseSalaryComponent) {
            return $baseSalary;
        }

        $amount = (float) $baseSalaryComponent->amount;

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => $baseSalaryComponent->salaryComponent->name,
            'type' => $baseSalaryComponent->salaryComponent->type,
            'calculation_type' => $baseSalaryComponent->salaryComponent->calculation_type,
            'quantity' => 1,
            'amount' => $amount,
            'source_type' => PayrollItemSourceEnum::BASE_SALARY->value,
        ]);

        $grossSalary += $amount;
        $baseSalary = $amount;

        return $baseSalary;
    }

    /**
     * Skip komponen kalau komponen gajinya punya syarat tipe karyawan TAPI tipe karyawan user
     * tidak ada di daftar itu. Komponen tanpa syarat (tidak ada baris pivot) berlaku semua tipe.
     * Eligibility ini di level katalog komponen, bukan per assignment.
     */
    private function isEligibleForEmployeeType(SalaryComponentAssignments $assignment, User $user): bool
    {
        $requiredTypeIds = $assignment->salaryComponent?->employeeTypes()->pluck('m_employee_types.id') ?? collect();

        if ($requiredTypeIds->isEmpty()) {
            return true;
        }

        return $requiredTypeIds->contains($user->profile?->employee_type_id);
    }

    private function handleJobPositionFixedComponents(
        User $user,
        Payroll $payroll,
        float &$grossSalary,
        float &$totalDeductions
    ): void {
        $components = $user->activeJobPosition?->jobPosition?->fixedSalaryComponents;

        if (empty($components)) {
            return;
        }

        foreach ($components as $component) {
            if (! $this->isEligibleForEmployeeType($component, $user)) {
                continue;
            }

            $amount = (float) $component->amount;

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $component->salaryComponent->name,
                'type' => $component->salaryComponent->type,
                'calculation_type' => $component->salaryComponent->calculation_type,
                'quantity' => 1,
                'amount' => $amount,
                'source_type' => PayrollItemSourceEnum::JOB_POSITION_ASSIGNMENT->value,
            ]);

            $this->applyImpactByType($component->salaryComponent->type, $amount, $grossSalary, $totalDeductions);
        }
    }

    private function handleJobPositionPercentageComponents(
        User $user,
        Payroll $payroll,
        float $baseSalary,
        float &$grossSalary,
        float &$totalDeductions
    ): void {
        $components = $user->activeJobPosition?->jobPosition?->percentageSalaryComponents;

        if (empty($components)) {
            return;
        }

        foreach ($components as $component) {
            if (! $this->isEligibleForEmployeeType($component, $user)) {
                continue;
            }

            $amount = ((float) $component->percentage / 100) * $baseSalary;

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $component->salaryComponent->name,
                'type' => $component->salaryComponent->type,
                'calculation_type' => $component->salaryComponent->calculation_type,
                'quantity' => $component->percentage,
                'unit' => CalculationTypeEnum::PERCENTAGE->value,
                'amount' => $amount,
                'source_type' => PayrollItemSourceEnum::JOB_POSITION_ASSIGNMENT->value,
            ]);

            $this->applyImpactByType($component->salaryComponent->type, $amount, $grossSalary, $totalDeductions);
        }
    }

    private function handleLeaveComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float &$grossSalary,
        float &$totalDeductions
    ): void {
        $component = $this->getMasterComponent(SalaryComponentCategoryEnum::CUTI, $period->company_id);
        if (! $component) {
            return;
        }

        $totalLeaveDays = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'leave')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('leaveDetails', function ($q) use ($period) {
                // TODO: implement overlap logic (not only start_date)
                $q->whereBetween('start_date', [$period->start_date, $period->end_date]);
            })
            ->with('leaveDetails')
            ->get()
            ->sum(fn ($request) => (float) $request->leaveDetails->sum('total_days'));

        if ($totalLeaveDays <= 0) {
            return;
        }

        $unitAmount = (float) $component->default_value; // per day
        $amount = $totalLeaveDays * $unitAmount;

        $this->createPayrollItemFromMaster(
            payroll: $payroll,
            component: $component,
            calculationType: CalculationTypeEnum::FIXED->value,
            quantity: $totalLeaveDays,
            unit: 'days',
            unitAmount: $unitAmount,
            amount: $amount,
            sourceType: PayrollItemSourceEnum::ATTENDANCE->value
        );

        $this->applyImpactByType($component->type, $amount, $grossSalary, $totalDeductions);
    }

    private function handlePermissionComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float &$grossSalary,
        float &$totalDeductions
    ): void {
        $component = $this->getMasterComponent(SalaryComponentCategoryEnum::IZIN, $period->company_id);
        if (! $component) {
            return;
        }

        $totalPermissionHours = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date]);
            })
            ->with('permissionDetails')
            ->get()
            ->sum(function ($request) {
                return (float) $request->permissionDetails->sum(function ($detail) {
                    $start = Carbon::parse($detail->start_time);
                    $end = Carbon::parse($detail->end_time);

                    return $start->diffInMinutes($end) / 60;
                });
            });

        $totalPermissionHours = round((float) $totalPermissionHours, 2);

        if ($totalPermissionHours <= 0) {
            return;
        }

        $unitAmount = (float) $component->default_value; // per hour
        $amount = $totalPermissionHours * $unitAmount;

        $this->createPayrollItemFromMaster(
            payroll: $payroll,
            component: $component,
            calculationType: CalculationTypeEnum::FIXED->value,
            quantity: $totalPermissionHours,
            unit: 'hours',
            unitAmount: $unitAmount,
            amount: $amount,
            sourceType: PayrollItemSourceEnum::ATTENDANCE->value
        );

        $this->applyImpactByType($component->type, $amount, $grossSalary, $totalDeductions);
    }

    private function handleOvertimeComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float $baseSalary,
        float &$grossSalary,
        float &$totalDeductions
    ): void {
        $overtimeRequests = AttendanceRequests::query()
            ->where('user_id', $user->id)
            ->where('type', 'overtime')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('overtimeDetails', function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date]);
            })
            ->with(['overtimeDetails' => function ($q) use ($period) {
                $q->whereBetween('date', [$period->start_date, $period->end_date]);
            }])
            ->get();

        if ($overtimeRequests->isEmpty()) {
            return;
        }

        foreach ($overtimeRequests as $request) {
            foreach ($request->overtimeDetails as $detail) {
                $actualMinutes = round((float) $detail->total_hours * 60, 2);

                if ($actualMinutes <= 0) {
                    continue;
                }

                $date = Carbon::parse($detail->date);

                // Cari shift user pada tanggal tersebut
                $shiftAssignment = ShiftScheduleAssignments::query()
                    ->where('user_id', $user->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                // Cari ShiftOvertimeCosts sesuai shift, jenis lembur, dan perusahaan
                $overtimeCost = ShiftOvertimeCosts::query()
                    ->where('company_id', $user->company_id)
                    ->when($shiftAssignment?->shift_id, fn ($q) => $q->where('shift_id', $shiftAssignment->shift_id))
                    ->when($detail->overtime_type_id, fn ($q) => $q->where('overtime_type_id', $detail->overtime_type_id))
                    ->first();

                if (! $overtimeCost || ! $overtimeCost->overtime_rate || ! $overtimeCost->maximum_overtime_hours) {
                    continue;
                }

                // maximum_overtime_hours adalah batas per hari; ubah ke menit
                $maxMinutes    = (float) $overtimeCost->maximum_overtime_hours * 60;
                $cappedMinutes = min($actualMinutes, $maxMinutes);

                // Tarif per menit: overtime_rate / (maximum_overtime_hours dalam menit)
                $ratePerMinute = (float) $overtimeCost->overtime_rate / $maxMinutes;
                $amount        = round($ratePerMinute * $cappedMinutes, 2);

                if ($amount <= 0) {
                    continue;
                }

                // Label durasi, misal "1j 30m"
                $totalMins     = (int) round($actualMinutes);
                $labelHours    = intdiv($totalMins, 60);
                $labelMins     = $totalMins % 60;
                $durationLabel = trim(($labelHours > 0 ? "{$labelHours} jam" : '') . ($labelMins > 0 ? " {$labelMins} menit" : ''));

                $dateLabel = $date->locale('id')->isoFormat('D MMMM YYYY');
                $itemName  = "Lembur - {$dateLabel} ({$durationLabel})";

                PayrollItem::create([
                    'payroll_id'       => $payroll->id,
                    'name'             => $itemName,
                    'type'             => 'earning',
                    'calculation_type' => CalculationTypeEnum::FIXED->value,
                    'quantity'         => round($cappedMinutes / 60, 4),
                    'unit'             => 'hours',
                    'unit_amount'      => round($ratePerMinute * 60, 4),
                    'amount'           => $amount,
                    'note'             => sprintf(
                        'Rate: Rp%s / %d jam, Aktual: %.0f menit, Dihitung: %.0f menit',
                        number_format((float) $overtimeCost->overtime_rate, 0, ',', '.'),
                        $overtimeCost->maximum_overtime_hours,
                        $actualMinutes,
                        $cappedMinutes
                    ),
                    'source_type' => PayrollItemSourceEnum::ATTENDANCE->value,
                ]);

                $grossSalary += $amount;
            }
        }
    }

    private function handleLateArrivalComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float &$grossSalary,
        float &$totalDeductions
    ): void {
        $component = $this->getMasterComponent(SalaryComponentCategoryEnum::KETERLAMBATAN, $period->company_id);

        if (! $component) {
            return;
        }

        $lateCount = AttendanceRecords::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('status', 'late')
            ->whereNotNull('check_in_time')
            ->get()
            ->count();

        if ($lateCount <= 0) {
            return;
        }

        $unitAmount = (float) $component->default_value; // per occurrence
        $amount = $lateCount * $unitAmount;

        $this->createPayrollItemFromMaster(
            payroll: $payroll,
            component: $component,
            calculationType: CalculationTypeEnum::FIXED->value,
            quantity: $lateCount,
            unit: 'occurrence',
            unitAmount: $unitAmount,
            amount: $amount,
            sourceType: PayrollItemSourceEnum::ATTENDANCE->value
        );

        $this->applyImpactByType(
            $component->type,
            $amount,
            $grossSalary,
            $totalDeductions
        );
    }


    private function handleExtraHourAllowanceComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float &$grossSalary
    ): void {
        // 1. Total menit kerja dari attendance records dalam periode
        $totalWorkingMinutes = (float) AttendanceRecords::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->whereNotNull('working_minutes')
            ->sum('working_minutes');

        $totalWorkingHours = round($totalWorkingMinutes / 60, 2);

        // 2. Ambil batas minimum jam kerja dari attendance policies
        // Prioritas: data spesifik perusahaan > data global (company_id = null)
        $policy = AttendancePolicies::query()
            ->where('type', 'min_total_working_hours_per_month')
            ->where(function ($q) use ($period) {
                $q->where('company_id', $period->company_id)
                  ->orWhereNull('company_id');
            })
            ->orderByRaw('(company_id IS NULL) ASC')
            ->first();

        $minWorkingHours = $policy ? (float) $policy->value : 0;

        // 3. Hitung kelebihan jam kerja
        $excessHours = ceil($totalWorkingHours - $minWorkingHours);
        Log::info("PayrollService: User {$user->id} - Total Working Hours: {$totalWorkingHours}, Minimum Required: {$minWorkingHours}, Excess Hours: {$excessHours}");

        if ($excessHours <= 0) {
            return;
        }

        // 4. Cari aturan tunjangan kelebihan jam kerja
        // Prioritas: aturan berdasarkan jabatan user, fallback ke aturan lain di perusahaan
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
            'payroll_id'       => $payroll->id,
            'name'             => 'Tunjangan Kelebihan Jam Kerja',
            'type'             => 'earning',
            'calculation_type' => CalculationTypeEnum::FIXED->value,
            'quantity'         => $excessHours,
            'unit'             => 'hours',
            'unit_amount'      => $amountPerHour,
            'amount'           => $amount,
            'note'             => sprintf(
                'Total jam: %.2f, Minimum: %.2f, Kelebihan: %.2f jam × Rp%s/jam',
                $totalWorkingHours,
                $minWorkingHours,
                $excessHours,
                number_format($amountPerHour, 0, ',', '.')
            ),
            'source_type' => PayrollItemSourceEnum::ATTENDANCE->value,
        ]);

        $grossSalary += $amount;
    }

    private function handleIncentiveComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float &$grossSalary
    ): void {
        $incentives = Incentive::query()
            ->where('user_id', $user->id)
            ->where('is_processed', false)
            ->whereBetween('incentive_date', [$period->start_date, $period->end_date])
            ->get();

        if ($incentives->isEmpty()) {
            return;
        }

        $totalAmount = (float) $incentives->sum('amount');

        $programNames = $incentives
            ->pluck('name')
            ->filter()
            ->unique()
            ->implode(', ');

        PayrollItem::create([
            'payroll_id'       => $payroll->id,
            'name'             => 'Incentive',
            'type'             => 'earning',
            'calculation_type' => CalculationTypeEnum::FIXED->value,
            'quantity'         => 1,
            'amount'           => $totalAmount,
            'note'             => $programNames ?: null,
            'source_type'      => PayrollItemSourceEnum::INCENTIVE->value,
        ]);

        $grossSalary += $totalAmount;

        Incentive::query()
            ->whereIn('id', $incentives->pluck('id'))
            ->update([
                'is_processed' => true,
            ]);
    }

    private function handleServiceFeeComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float &$grossSalary
    ): void {
        // Master salary component
        $component = $this->getMasterComponent(SalaryComponentCategoryEnum::JASA_PELAYANAN, $period->company_id);
        if (! $component) {
            return;
        }

        // User job position & JE score
        $jobPosition = $user->activeJobPosition?->jobPosition;
        if (! $jobPosition || ! $jobPosition->job_evaluation_score) {
            return;
        }

        $userJeScore = (float) $jobPosition->job_evaluation_score;

        // Total JE company (by payroll month & year)
        $periodDate = Carbon::parse($period->start_date);

        $companyJe = JobEvaluationCompanies::query()
            ->where('company_id', $period->company_id)
            ->where('month', $periodDate->monthName)
            ->where('year', $periodDate->year)
            ->first();

        if (! $companyJe || $companyJe->score <= 0) {
            return;
        }

        $totalCompanyJe = (float) $companyJe->score;

        // Total service fee company (company salary component)
        $company = Companies::find($period->company_id);

        // $component->name dipakai (bukan enum lagi) — komponen ini sudah ketemu via
        // category di atas, jadi tinggal pakai nama persisnya buat cocokin assignment.
        $companyServiceFeeComponent = $company?->selectedSalaryComponent($component->name);

        if (! $companyServiceFeeComponent || $companyServiceFeeComponent->amount <= 0) {
            return;
        }

        $totalCompanyServiceFee = (float) $companyServiceFeeComponent->amount;

        // Calculate service fee
        $ratio = $userJeScore / $totalCompanyJe;
        $amount = round($ratio * $totalCompanyServiceFee, 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id'       => $payroll->id,
            'name'             => $component->name,
            'type'             => $component->type,
            'calculation_type' => CalculationTypeEnum::FIXED->value,
            'quantity'         => 1,
            'amount'           => $amount,
            'note'             => sprintf(
                'JE %.2f / %.2f × %s',
                $userJeScore,
                $totalCompanyJe,
                number_format($totalCompanyServiceFee, 0)
            ),
            'source_type' => PayrollItemSourceEnum::SERVICE_FEE->value,
        ]);

        $grossSalary += $amount;
    }

    /**
     * Klaim fee per kejadian (mis. jasa dokter) — 1 baris payroll_items per FeeItem (bukan
     * digabung, beda dari handleIncentiveComponent, karena tiap fee perlu terlihat rinciannya
     * di slip), plus 1 baris pajak kalau totalnya > 0.
     */
    private function handleFeeItemsComponent(
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        float &$grossSalary,
        float &$totalDeductions
    ): void {
        $feeItems = FeeItem::query()
            ->where('user_id', $user->id)
            ->where('is_paid', false)
            ->whereHas('submission', function ($q) use ($period) {
                $q->where('company_id', $period->company_id)
                    ->whereBetween('date', [$period->start_date, $period->end_date]);
            })
            ->get();

        if ($feeItems->isEmpty()) {
            return;
        }

        $totalFee = 0.0;

        foreach ($feeItems as $item) {
            $amount = (float) $item->amount;

            PayrollItem::create([
                'payroll_id'       => $payroll->id,
                'name'             => $item->name,
                'type'             => 'earning',
                'calculation_type' => CalculationTypeEnum::FIXED->value,
                'quantity'         => 1,
                'amount'           => $amount,
                'source_type'      => PayrollItemSourceEnum::FEE_CLAIM->value,
            ]);

            $grossSalary += $amount;
            $totalFee += $amount;
        }

        $taxComponent = $this->getMasterComponent(SalaryComponentCategoryEnum::PAJAK_JASA_DOKTER, $period->company_id);

        if ($taxComponent && $totalFee > 0) {
            $taxPercentage = (float) $taxComponent->default_value;
            $taxAmount = round($totalFee * $taxPercentage / 100, 2);

            if ($taxAmount > 0) {
                PayrollItem::create([
                    'payroll_id'       => $payroll->id,
                    'name'             => $taxComponent->name,
                    'type'             => 'deduction',
                    'calculation_type' => CalculationTypeEnum::PERCENTAGE->value,
                    'quantity'         => $taxPercentage,
                    'unit'             => CalculationTypeEnum::PERCENTAGE->value,
                    'amount'           => $taxAmount,
                    'source_type'      => PayrollItemSourceEnum::FEE_CLAIM->value,
                ]);

                $totalDeductions += $taxAmount;
            }
        }

        FeeItem::query()
            ->whereIn('id', $feeItems->pluck('id'))
            ->update(['is_paid' => true]);
    }

    private function getMasterComponent(SalaryComponentCategoryEnum $categoryEnum, string $companyId): ?SalaryComponents
    {
        return SalaryComponents::query()
            ->where('category', $categoryEnum->value)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderByRaw('company_id IS NULL')
            ->first();
    }

    private function createPayrollItemFromMaster(
        Payroll $payroll,
        SalaryComponents $component,
        string $calculationType,
        float $quantity,
        string $unit,
        float $unitAmount,
        float $amount,
        string $sourceType
    ): void {
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => $component->name,
            'type' => $component->type,
            'calculation_type' => $calculationType,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_amount' => $unitAmount,
            'amount' => $amount,
            'source_type' => $sourceType,
        ]);
    }

    /**
     * Apply salary impact based on type string ('earning' vs 'deduction').
     */
    private function applyImpactByType(string $type, float $amount, float &$grossSalary, float &$totalDeductions): void
    {
        if ($type === 'earning') {
            $grossSalary += $amount;
            return;
        }

        $totalDeductions += $amount;
    }
}
