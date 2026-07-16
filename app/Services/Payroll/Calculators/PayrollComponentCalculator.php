<?php

namespace App\Services\Payroll\Calculators;

use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

interface PayrollComponentCalculator
{
    /**
     * Hitung & buat PayrollItem (0 atau lebih baris) untuk 1 user, 1 periode. Panggil
     * $context->addEarning()/addDeduction() buat tiap baris yang dibuat — JANGAN sentuh
     * $payroll->gross_salary/total_deductions langsung, orchestrator yang menjumlahkan dari
     * PayrollContext di akhir (1 sumber kebenaran).
     */
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void;
}
