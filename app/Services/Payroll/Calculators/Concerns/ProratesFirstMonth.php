<?php

namespace App\Services\Payroll\Calculators\Concerns;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;

/**
 * Prorate bulan pertama untuk karyawan yang Tanggal Bergabungnya jatuh di tengah periode berjalan
 * — dipakai bareng oleh Gaji Pokok, Tunjangan Jabatan, Tunjangan Transportasi, Tunjangan Masa
 * Kerja (dikonfirmasi user 2026-08-05, awalnya cuma Gaji Pokok). Rasio & pembagi SATU sumber
 * di sini supaya keempatnya konsisten, bukan hitung ulang beda-beda di tiap Calculator.
 *
 * PEMBAGI company-scoped lewat setting `prorate_metode` (halaman Komponen Gaji, section
 * "Pengaturan Lainnya" — setara UMP, bukan punya 1 komponen tertentu):
 * - 'kalender' (default) = jumlah hari kalender di BULAN Tanggal Bergabung (28-31).
 * - 'hari_kerja_efektif' = angka TETAP dari KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN
 *   (Pengaturan Kepegawaian, default 25) - sama pembaginya dengan IzinSeharianGajiPokokCalculator.
 *
 * Pembilang: hari Senin-Sabtu (exclude Minggu saja, tanpa lihat jadwal/roster/hari libur per
 * karyawan - sengaja sederhana) dari Tanggal Bergabung s.d. akhir periode.
 */
trait ProratesFirstMonth
{
    /**
     * @return array{ratio: float, note: string}|null null = tidak perlu diprorate (karyawan
     * lama, atau Tanggal Bergabung belum diisi / di luar periode ini).
     */
    protected function firstMonthProrate(User $user, PayrollPeriod $period): ?array
    {
        // Tanggal Bergabung - kolom string biasa di DB (bukan di-cast Carbon oleh model).
        $joiningDate = $user->profile?->joining_date ? Carbon::parse($user->profile->joining_date) : null;

        // Syarat prorate: (1) Tanggal Bergabung ADA, (2) jatuh SETELAH awal periode (baru masuk
        // di tengah bulan, bukan sudah dari awal), (3) masih di dalam rentang periode ini.
        if (! $joiningDate || ! $joiningDate->gt($period->start_date) || ! $joiningDate->lte($period->end_date)) {
            return null;
        }

        $metode = Setting::resolve('prorate_metode', $period->company_id, default: 'kalender');

        if ($metode === 'hari_kerja_efektif') {
            $divisor = (int) Setting::resolve(
                KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN->value,
                $period->company_id,
                default: KepegawaianSettingEnum::HARI_KERJA_EFEKTIF_PER_BULAN->default()
            );
            $divisorLabel = 'hari kerja efektif';
        } else {
            $divisor = $joiningDate->daysInMonth;
            $divisorLabel = 'hari kalender';
        }

        if ($divisor <= 0) {
            return null;
        }

        $workedDays = $this->countProrateWorkDays($joiningDate, $period->end_date);
        // Rasio dikunci maksimal 1 (100%) - kalau workedDays kebetulan melebihi pembaginya,
        // tidak boleh dibayar lebih dari 1 bulan penuh.
        $ratio = min(1, $workedDays / $divisor);

        return [
            'ratio' => $ratio,
            'note' => "prorate bulan pertama {$workedDays}/{$divisor} {$divisorLabel} sejak {$joiningDate->format('d-m-Y')}",
        ];
    }

    /** Hari Senin-Sabtu dari $from s.d. $to (inklusif) - Minggu di-exclude. */
    private function countProrateWorkDays(Carbon $from, Carbon $to): int
    {
        $count = 0;

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if (! $date->isSunday()) {
                $count++;
            }
        }

        return $count;
    }
}
