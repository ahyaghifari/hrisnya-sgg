<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\JobPositionReqStatus;
use App\Enum\Payroll\OmsetPayerType;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\JobPositions;
use App\Models\Payroll\OmsetDetail;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Employee\Employee;

/**
 * Rasio JE user / total JE company x Total Omset periode itu — logic sama persis
 * PayrollService::handleServiceFeeComponent() lama. Total JE company dihitung LIVE
 * (SUM job_evaluation_score semua jabatan APPROVED company itu, bukan input manual terpisah
 * — job_evaluation_companies lama sudah tidak dipakai). job_evaluation_score sendiri diedit
 * satu-satunya di Kepegawaian (App\Livewire\Kepegawaian\JobEvaluationPage), bukan di sini.
 * Total Omset dijumlah dari omset_details per periode (rincian per kategori, dikelola di
 * Mapping Komponen Gaji + halaman Omset di Keuangan — service_fee_pools lama sudah tidak
 * dipakai), lalu tiap kategori diambil persentasenya sesuai jenis penjamin (OmsetPayerType:
 * BPJS 2,5%, Asuransi/Umum 7,5%) - hasilnya yang jadi pool dibagi per rasio JE.
 */
class JasaPelayananCalculator implements PayrollComponentCalculator
{
    public function calculate(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        // Jabatan SAAT INI dari employees.job_position_id (instruksi user 2026-10-17), bukan riwayat jabatan akun.
        $jobPosition = $employee->jobPosition;

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

        // `amount` dienkripsi (PayrollEncrypted) - SUM SQL gak bisa jalan, jumlah di PHP.
        // Pool = omset tiap kategori x persentase penjamin kategori itu (BPJS 2,5% / Umum 7,5%).
        $totalCompanyServiceFee = (float) OmsetDetail::query()
            ->where('payroll_period_id', $period->id)
            ->with('category:id,payer_type')
            ->get()
            ->sum(fn (OmsetDetail $d) => (float) $d->amount * ($d->category?->payer_type ?? OmsetPayerType::UMUM)->rate());

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
