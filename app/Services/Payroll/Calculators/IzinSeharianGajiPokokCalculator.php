<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Employee\Employee;
use App\Services\Payroll\Calculators\Concerns\ResolvesProrateDivisor;

/**
 * Metode 'gaji_pokok' (rumus RS lain, beda dari 'keseluruhan'):
 *   rate_harian = (Gaji Pokok / PEMBAGI) x persen_potongan
 *
 * Beda dari IzinSeharianKeseluruhanCalculator: basisnya Gaji Pokok SAJA (bukan gross), dan ADA
 * persentase configurable (setting `izin_seharian_persen_gaji_pokok`, default 70) - metode
 * 'keseluruhan' tidak ada persen sama sekali.
 *
 * PEMBAGI company-scoped lewat setting `prorate_metode` (ResolvesProrateDivisor, sama sumbernya
 * dengan Gaji Pokok/Tunjangan/IzinSeharianKeseluruhanCalculator - dikonfirmasi user 2026-08-05).
 * Sebelumnya SELALU pakai KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN tanpa bisa
 * diganti - sekarang cuma dipakai kalau company itu set `prorate_metode` = 'hari_kerja_efektif',
 * default-nya (kalender) sama seperti komponen lain.
 *
 * Nominal Gaji Pokok diambil dari PayrollItem yang sudah dibuat GajiPokokCalculator di payroll
 * ini (Gaji Pokok jalan duluan sebelum deduction manapun), bukan dihitung ulang - otomatis ikut
 * prorate kalau karyawan itu baru bergabung bulan pertama.
 */
class IzinSeharianGajiPokokCalculator extends AbstractIzinSeharianCalculator
{
    use ResolvesProrateDivisor;

    protected function rateHarian(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): float
    {
        // `amount` dienkripsi (PayrollEncrypted) - SUM SQL gak bisa jalan, jumlah di PHP.
        $gajiPokok = (float) PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)
            ->get(['amount'])
            ->sum('amount');

        if ($gajiPokok <= 0) {
            return 0.0;
        }

        $divisor = $this->prorateDivisor($period, $period->end_date);

        if ($divisor <= 0) {
            return 0.0;
        }

        // Persentase dari rate harian Gaji Pokok yang dipotong per hari izin (default 70%).
        $persen = (float) Setting::resolve('izin_seharian_persen_gaji_pokok', $period->company_id, default: '70') / 100;

        return ($gajiPokok / $divisor) * $persen;
    }
}
