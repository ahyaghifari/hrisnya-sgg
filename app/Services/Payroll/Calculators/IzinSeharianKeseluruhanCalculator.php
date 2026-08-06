<?php

namespace App\Services\Payroll\Calculators;

use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\Calculators\Concerns\ResolvesProrateDivisor;

/**
 * Metode 'keseluruhan' (dikonfirmasi user dari Excel analisis-payroll-4 §7B.3):
 *   rate_harian = T8 / PEMBAGI
 * T8 = total pendapatan SELURUH komponen earning — HARUS jalan setelah semua komponen EARNING
 * (+ Fee Claim) terkumpul di PayrollContext, lihat urutan orchestrator di PayrollService
 * (Izin Seharian dipaksa jalan PALING TERAKHIR supaya $context->grossSalary sudah final).
 *
 * PEMBAGI company-scoped lewat setting `prorate_metode` (ResolvesProrateDivisor, sama sumbernya
 * dengan Gaji Pokok/Tunjangan/IzinSeharianGajiPokokCalculator - dikonfirmasi user 2026-08-05,
 * sebelumnya hardcode jumlah hari kalender bulan periode terus tanpa bisa diganti).
 *
 * TIDAK ada persentase di metode ini - beda dari IzinSeharianGajiPokokCalculator yang dikali
 * persen configurable.
 */
class IzinSeharianKeseluruhanCalculator extends AbstractIzinSeharianCalculator
{
    use ResolvesProrateDivisor;

    protected function rateHarian(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): float
    {
        $divisor = $this->prorateDivisor($period, $period->end_date);

        // Guard: tanpa pembagi atau tanpa gross salary, tidak bisa hitung rate harian.
        if ($divisor <= 0 || $context->grossSalary <= 0) {
            return 0.0;
        }

        // Rate harian = total gross salary (T8, sudah final karena Izin Seharian dijalankan
        // paling terakhir) dibagi pembagi (hari kalender atau hari kerja efektif, tergantung setting).
        return $context->grossSalary / $divisor;
    }
}
