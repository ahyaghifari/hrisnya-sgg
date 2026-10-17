<?php

namespace App\Services\Payroll;

use App\Enum\EmployeeTypeCategory;
use App\Models\Payroll\GajiPokokKenaikanOverride;
use App\Models\Payroll\Payroll;
use App\Models\Setting;

/**
 * Base gaji & persen kenaikan flat - rumus sama persis GajiPokokCalculator (TANPA prorate bulan
 * pertama, dipakai buat referensi basis perhitungan di export/halaman rekap, bukan nominal final
 * payroll_items). Diekstrak dari PayrollSlipRekapExport (2026-08-22) supaya dipakai bareng oleh
 * PajakGajiPokokExport/PajakPage tanpa duplikasi rumus ketiga kalinya.
 */
class GajiPokokBreakdownResolver
{
    /** @return array{0: ?float, 1: ?float} [base, percentKenaikan] */
    public static function resolve(Payroll $payroll): array
    {
        $profile = $payroll->employee;
        $pangkat = $profile?->pangkat;
        $kelas = $profile?->kelas;
        $pangkatSalary = $pangkat?->pangkatSalary;

        if (! $pangkat || ! $kelas || ! $pangkatSalary || $kelas->level === null) {
            return [null, null];
        }

        // Orientasi ATAU employeeType belum di-assign (null) tidak dapat kenaikan sama sekali -
        // sama persis fail-safe di GajiPokokCalculator, wajib tetap sinkron (rumus duplikat).
        $category = $profile->employeeType?->category;
        $rate = ($category === null || $category === EmployeeTypeCategory::ORIENTASI)
            ? 0.0
            : (GajiPokokKenaikanOverride::activeRateFor($payroll->employee_id)
                ?? (float) Setting::resolve('gaji_pokok_kenaikan_per_kelas', $payroll->payrollPeriod?->company_id, default: 8));

        return [(float) $pangkatSalary->base_salary, $rate];
    }
}
