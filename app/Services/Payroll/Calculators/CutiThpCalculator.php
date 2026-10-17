<?php

namespace App\Services\Payroll\Calculators;

use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Employee\Employee;

/**
 * Teknik 'thp': satu baris potongan dari selisih THP (net_salary) penuh vs proyeksi net kalau
 * sebagian hari masih masuk kerja (porsi THP) + sebagian hari cuti (porsi Gaji Pokok saja).
 *
 * THP = net running total SAAT INI ($context->grossSalary - $context->totalDeductions) - VALID
 * cuma karena CutiCalculator (dispatcher, lihat PayrollCalculatorRegistry) dipaksa jalan PALING
 * TERAKHIR (lihat PayrollService::generateForEmployee()), jadi semua earning+deduction lain
 * (termasuk Gaji Pokok, BPJS, Izin Seharian) sudah final duluan.
 *
 * Potongan = THP - ( THP/E x W + GajiPokokAkhir x rasio_gaji_pokok )
 * W = max(0, E-L) - hari kerja efektif yang masih dijalani.
 * rasio_gaji_pokok = min(1, L/E) - dikunci maks 100% (cuti penuh 1 periode -> Gaji Pokok
 * dibayar PENUH, bukan diprorate lagi) - dikonfirmasi user 2026-09-05, dua teknik konvergen
 * ke hasil yang sama di kasus ini (CutiPerKomponenCalculator juga zero-in ke Gaji Pokok doang).
 */
class CutiThpCalculator extends AbstractCutiMelahirkanCalculator
{
    protected function resolveAmount(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context, int $divisor, int $days): ?float
    {
        $thp = $context->grossSalary - $context->totalDeductions;
        $gajiPokokAkhir = app(GajiPokokCalculator::class)->resolveAmount($employee, $period) ?? 0.0;

        $worked = max(0, $divisor - $days);
        $gajiPokokRatio = min(1, $days / $divisor);

        $projected = ($thp / $divisor * $worked) + ($gajiPokokAkhir * $gajiPokokRatio);

        return $thp - $projected;
    }
}
