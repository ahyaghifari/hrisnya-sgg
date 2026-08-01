<?php

namespace App\Services\Payroll\Calculators;

use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;

/**
 * Metode 'keseluruhan' (dikonfirmasi user dari Excel analisis-payroll-4 §7B.3):
 *   rate_harian = T8 / DAY(EOMONTH(periode, 0))
 * T8 = total pendapatan SELURUH komponen earning — HARUS jalan setelah semua komponen EARNING
 * (+ Fee Claim) terkumpul di PayrollContext, lihat urutan orchestrator di PayrollService
 * (Izin Seharian dipaksa jalan PALING TERAKHIR supaya $context->grossSalary sudah final).
 *
 * TIDAK ada persentase di metode ini - beda dari IzinSeharianGajiPokokCalculator yang dikali
 * persen configurable.
 */
class IzinSeharianKeseluruhanCalculator extends AbstractIzinSeharianCalculator
{
    protected function rateHarian(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): float
    {
        // Jumlah hari kalender di bulan periode ini (mis. 31 untuk Juli).
        $hariDalamBulan = $period->end_date->daysInMonth;

        // Guard: tanpa hari dalam bulan atau tanpa gross salary, tidak bisa hitung rate harian.
        if ($hariDalamBulan <= 0 || $context->grossSalary <= 0) {
            return 0.0;
        }

        // Rate harian = total gross salary (T8, sudah final karena Izin Seharian dijalankan
        // paling terakhir) dibagi hari kalender sebulan.
        return $context->grossSalary / $hariDalamBulan;
    }
}
