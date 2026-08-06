<?php

namespace App\Services\Payroll\Calculators\Concerns;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * SATU sumber pembagi "prorate"/rate-harian company-scoped lewat setting `prorate_metode`
 * (halaman Komponen Gaji > Pengaturan Lainnya - setara UMP, bukan punya 1 komponen tertentu).
 * Dipakai di SELURUH proses yang butuh "nominal per hari" (dikonfirmasi user 2026-08-05):
 * ProratesFirstMonth (Gaji Pokok/Tunjangan Jabatan/Transportasi/Masa Kerja bulan pertama
 * karyawan baru), IzinSeharianKeseluruhanCalculator, IzinSeharianGajiPokokCalculator - bukan
 * masing-masing hardcode pembaginya sendiri-sendiri.
 *
 * - 'kalender' (default) = jumlah hari kalender di bulan $referenceDate (28-31).
 * - 'hari_kerja_efektif' = angka TETAP dari KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN
 *   (Pengaturan Kepegawaian, default 25) - $referenceDate diabaikan sama sekali di mode ini.
 */
trait ResolvesProrateDivisor
{
    protected function prorateDivisor(PayrollPeriod $period, Carbon $referenceDate): int
    {
        $metode = Setting::resolve('prorate_metode', $period->company_id, default: 'kalender');

        if ($metode === 'hari_kerja_efektif') {
            return (int) Setting::resolve(
                KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN->value,
                $period->company_id,
                default: KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN->default()
            );
        }

        return $referenceDate->daysInMonth;
    }

    protected function prorateDivisorLabel(PayrollPeriod $period): string
    {
        return Setting::resolve('prorate_metode', $period->company_id, default: 'kalender') === 'hari_kerja_efektif'
            ? 'hari kerja efektif'
            : 'hari kalender';
    }
}
