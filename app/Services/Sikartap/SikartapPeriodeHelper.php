<?php

namespace App\Services\Sikartap;

use Carbon\Carbon;

/**
 * Rewrite periode_pengajuan.php legacy — pisah pengajuan "periode berjalan" (≤3 bulan) dari
 * "periode lama" (>3 bulan), lalu kelompokkan periode lama jadi batch 6-bulanan mundur dari
 * batas periode berjalan. Dipakai dashboard_hrd.php/dashboard_direktur.php/
 * dashboard_verifikator_inovasi.php legacy buat tab "Periode Berjalan/Lama" + sub-tab Batch.
 *
 * Sengaja TIDAK port fitur $BATAS_BATCH_MANUAL (tanggal potong batch manual per siklus) - kalau
 * siklus pengajuan riil nanti butuh potongan custom (bukan rapi 6 bulanan), tambahkan nanti,
 * bukan sekarang (YAGNI).
 */
class SikartapPeriodeHelper
{
    public const BATAS_PERIODE_BULAN = 3;

    public const LEBAR_BATCH_BULAN = 6;

    public static function batasPeriode(): Carbon
    {
        return now()->subMonths(self::BATAS_PERIODE_BULAN);
    }

    public static function isPeriodeLama(?Carbon $tanggal): bool
    {
        return $tanggal !== null && $tanggal->lt(self::batasPeriode());
    }

    /** Tepi terbaru batch ke-$n (n=1 = batas periode berjalan). */
    public static function batasBatch(int $n): Carbon
    {
        return self::batasPeriode()->copy()->subMonths(self::LEBAR_BATCH_BULAN * max(0, $n - 1));
    }

    public static function nomorBatch(?Carbon $tanggal): int
    {
        if (! self::isPeriodeLama($tanggal)) {
            return 0;
        }

        for ($n = 1; $n <= 400; $n++) {
            if ($tanggal->gte(self::batasBatch($n + 1))) {
                return $n;
            }
        }

        return 400;
    }

    public static function labelRentangBatch(int $n): string
    {
        return self::batasBatch($n + 1)->format('d/m/Y') . ' – ' . self::batasBatch($n)->format('d/m/Y');
    }

    public static function labelUmur(?Carbon $tanggal): string
    {
        if (! $tanggal) {
            return 'tanggal tidak tercatat';
        }

        return $tanggal->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]);
    }
}
