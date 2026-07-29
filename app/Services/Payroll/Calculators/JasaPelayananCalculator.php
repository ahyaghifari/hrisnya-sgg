<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\JobPositionReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\JobPositions;
use App\Models\Payroll\OmsetDetail;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Rasio JE user / total JE company x Total Omset periode itu — logic sama persis
 * PayrollService::handleServiceFeeComponent() lama. Total JE company dihitung LIVE
 * (SUM job_evaluation_score semua jabatan APPROVED company itu, bukan input manual terpisah
 * — job_evaluation_companies lama sudah tidak dipakai). job_evaluation_score sendiri diedit
 * satu-satunya di Kepegawaian (App\Livewire\Kepegawaian\JobEvaluationPage), bukan di sini.
 * Total Omset dijumlah dari omset_details per periode (rincian per kategori, dikelola di
 * Mapping Komponen Gaji + halaman Omset di Keuangan — service_fee_pools lama sudah tidak
 * dipakai).
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

        $totalCompanyJe = (float) JobPositions::query()
            ->where('company_id', $period->company_id)
            ->where('status', JobPositionReqStatus::APPROVED->value)
            ->sum('job_evaluation_score');

        if ($totalCompanyJe <= 0) {
            return;
        }

        $totalCompanyServiceFee = (float) OmsetDetail::query()
            ->where('payroll_period_id', $period->id)
            ->sum('amount');

        if ($totalCompanyServiceFee <= 0) {
            return;
        }

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
