<?php

namespace App\Services\Payroll\Calculators;

use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * Dispatcher tipis - pilih Calculator sesuai setting `izin_seharian_metode` (company-scoped,
 * default 'keseluruhan'), dipilih di halaman Mapping Komponen Gaji. Logika hitungnya sendiri
 * ada di 2 class terpisah (bukan percabangan private method di sini), supaya masing-masing
 * metode gampang dibaca dan ditest sendiri-sendiri:
 * - IzinSeharianKeseluruhanCalculator — dari total gross salary, TANPA persen.
 * - IzinSeharianGajiPokokCalculator — dari Gaji Pokok saja, DIKALI persen configurable.
 *
 * Tetap terdaftar sebagai 1 entry di PayrollCalculatorRegistry (IZIN_SEHARIAN => self::class)
 * supaya orchestrator (PayrollService) tidak perlu tahu ada 2 metode di baliknya.
 */
class IzinSeharianCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $metode = Setting::resolve('izin_seharian_metode', $period->company_id, default: 'keseluruhan');

        $calculator = $metode === 'gaji_pokok'
            ? new IzinSeharianGajiPokokCalculator()
            : new IzinSeharianKeseluruhanCalculator();

        $calculator->calculate($user, $payroll, $period, $context);
    }
}
