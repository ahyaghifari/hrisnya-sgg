<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\JobEvaluationCompanies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\ServiceFeePool;
use App\Models\User;
use Carbon\Carbon;

/**
 * Rasio JE user / total JE company x pool "Total Jaspel" bulan itu — logic sama persis
 * PayrollService::handleServiceFeeComponent() lama, cuma sumber pool pindah dari
 * salary_component_assignments (Company) ke service_fee_pools (Fase 1, tabel baru — pool ini
 * angka BULANAN, bukan config stabil, lihat planning §3.3).
 */
class JasaPelayananCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $jobPosition = $user->activeJobPosition?->jobPosition;

        if (! $jobPosition || ! $jobPosition->job_evaluation_score) {
            return;
        }

        $userJeScore = (float) $jobPosition->job_evaluation_score;

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

        $pool = ServiceFeePool::query()
            ->where('company_id', $period->company_id)
            ->where('payroll_period_id', $period->id)
            ->first();

        if (! $pool || $pool->total_amount <= 0) {
            return;
        }

        $totalCompanyServiceFee = (float) $pool->total_amount;

        $ratio = $userJeScore / $totalCompanyJe;
        $amount = round($ratio * $totalCompanyServiceFee, 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::JASA_PELAYANAN->label(),
            'type' => PayrollComponentEnum::JASA_PELAYANAN->type()->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => sprintf('JE %.2f / %.2f × %s', $userJeScore, $totalCompanyJe, number_format($totalCompanyServiceFee, 0)),
            'source_type' => PayrollComponentEnum::JASA_PELAYANAN->value,
        ]);

        $context->addEarning($amount);
    }
}
