<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

/**
 * Metode 'gaji_pokok' (rumus RS lain, beda dari 'keseluruhan'):
 *   rate_harian = (Gaji Pokok / Hari Kerja Efektif per Bulan) x persen_potongan
 *
 * Beda dari IzinSeharianKeseluruhanCalculator: basisnya Gaji Pokok SAJA (bukan gross), dan ADA
 * persentase configurable (setting `izin_seharian_persen_gaji_pokok`, default 70) - metode
 * 'keseluruhan' tidak ada persen sama sekali.
 *
 * "Hari Kerja Efektif per Bulan" reuse setting yang sama dipakai prorate Gaji Pokok Orientasi
 * (KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN), supaya tidak dobel setting untuk
 * konsep yang sama. Nominal Gaji Pokok diambil dari PayrollItem yang sudah dibuat
 * GajiPokokCalculator di payroll ini (Gaji Pokok jalan duluan sebelum deduction manapun),
 * bukan dihitung ulang - otomatis ikut prorate kalau karyawan itu Orientasi bulan pertama.
 */
class IzinSeharianGajiPokokCalculator extends AbstractIzinSeharianCalculator
{
    protected function rateHarian(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): float
    {
        $gajiPokok = (float) PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)
            ->sum('amount');

        if ($gajiPokok <= 0) {
            return 0.0;
        }

        $hariKerjaEfektifPerBulan = (int) Setting::resolve(
            KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN->value,
            $period->company_id,
            default: KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN->default()
        );

        if ($hariKerjaEfektifPerBulan <= 0) {
            return 0.0;
        }

        // Persentase dari rate harian Gaji Pokok yang dipotong per hari izin (default 70%).
        $persen = (float) Setting::resolve('izin_seharian_persen_gaji_pokok', $period->company_id, default: '70') / 100;

        return ($gajiPokok / $hariKerjaEfektifPerBulan) * $persen;
    }
}
