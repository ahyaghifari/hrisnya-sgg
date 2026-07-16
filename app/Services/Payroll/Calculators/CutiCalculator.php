<?php

namespace App\Services\Payroll\Calculators;

use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Cuti TIDAK ADA POTONGAN (dikonfirmasi user) — no-op sepenuhnya, tidak buat baris
 * payroll_items sama sekali (baris Rp0 cuma nambah noise di slip tanpa nilai).
 */
class CutiCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        // sengaja kosong
    }
}
