<?php

namespace App\Services\Payroll\Calculators\Concerns;

use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;

/**
 * Prorate bulan pertama untuk karyawan yang Tanggal Bergabungnya jatuh di tengah periode berjalan
 * — dipakai bareng oleh Gaji Pokok, Tunjangan Jabatan, Tunjangan Transportasi, Tunjangan Masa
 * Kerja (dikonfirmasi user 2026-08-05, awalnya cuma Gaji Pokok). Rasio & pembagi SATU sumber
 * di sini supaya keempatnya konsisten, bukan hitung ulang beda-beda di tiap Calculator.
 *
 * PEMBAGI di-resolve lewat ResolvesProrateDivisor (setting `prorate_metode`, sama sumbernya
 * dengan IzinSeharianKeseluruhanCalculator/IzinSeharianGajiPokokCalculator - dikonfirmasi user
 * 2026-08-05, awalnya cuma dipakai di sini), referensi bulannya BULAN Tanggal Bergabung
 * (bukan bulan periode - relevan kalau periode custom cutoff-nya lintas bulan).
 *
 * Pembilang: hari Senin-Sabtu (exclude Minggu saja, tanpa lihat jadwal/roster/hari libur per
 * karyawan - sengaja sederhana) dari Tanggal Bergabung s.d. akhir periode.
 */
trait ProratesFirstMonth
{
    use ResolvesProrateDivisor;

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

        $divisor = $this->prorateDivisor($period, $joiningDate);

        if ($divisor <= 0) {
            return null;
        }

        $workedDays = $this->countProrateWorkDays($joiningDate, $period->end_date);
        // Rasio dikunci maksimal 1 (100%) - kalau workedDays kebetulan melebihi pembaginya,
        // tidak boleh dibayar lebih dari 1 bulan penuh.
        $ratio = min(1, $workedDays / $divisor);

        return [
            'ratio' => $ratio,
            'note' => "prorate bulan pertama {$workedDays}/{$divisor} {$this->prorateDivisorLabel($period)} sejak {$joiningDate->format('d-m-Y')}",
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
