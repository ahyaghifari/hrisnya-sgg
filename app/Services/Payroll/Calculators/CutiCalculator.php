<?php

namespace App\Services\Payroll\Calculators;

use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * Dispatcher tipis - Cuti Tahunan/Haid TIDAK ADA POTONGAN (dikonfirmasi user) - kalau tidak ada
 * cuti Melahirkan/Keguguran APPROVED yang overlap periode ini, dua-duanya no-op total lewat
 * AbstractCutiMelahirkanCalculator::calculate(). MELAHIRKAN/KEGUGURAN beda (instruksi user
 * 2026-09-05): SEMUA hari cutinya dipotong, pilih Calculator sesuai setting
 * `cuti_melahirkan_metode` (company-scoped, halaman Komponen Gaji, default 'per_komponen').
 * Logika hitungnya sendiri ada di 2 class terpisah (bukan percabangan private method di sini),
 * pola sama IzinSeharianCalculator - supaya masing-masing teknik gampang dibaca dan ditest
 * sendiri-sendiri:
 * - CutiPerKomponenCalculator — prorate Tunjangan Jabatan/Transportasi/Masa Kerja, Insentif,
 *   Jasa Pelayanan sesuai hari kerja efektif yang dijalani.
 * - CutiThpCalculator — 1 baris potongan dari selisih THP, WAJIB jalan paling terakhir (lihat
 *   PayrollService::generateForEmployee()).
 *
 * Tetap terdaftar sebagai 1 entry di PayrollCalculatorRegistry (CUTI => self::class) supaya
 * orchestrator (PayrollService) tidak perlu tahu ada 2 teknik di baliknya.
 */
class CutiCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $metode = Setting::resolve('cuti_melahirkan_metode', $period->company_id, default: 'per_komponen');

        $calculator = $metode === 'thp'
            ? new CutiThpCalculator()
            : new CutiPerKomponenCalculator();

        $calculator->calculate($user, $payroll, $period, $context);
    }
}
