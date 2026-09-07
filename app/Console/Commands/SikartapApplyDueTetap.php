<?php

namespace App\Console\Commands;

use App\Services\Sikartap\SikartapDirectorDecisionService;
use Illuminate\Console\Command;

/**
 * Terapkan pengangkatan Karyawan Tetap (Sikartap) yang keputusan Direkturnya sudah `approved`
 * tapi tanggal TMT-nya di masa depan — begitu tanggalnya tiba. Dijadwalkan harian di
 * bootstrap/app.php. TMT yang <= hari approve sudah langsung diterapkan saat approve.
 */
class SikartapApplyDueTetap extends Command
{
    protected $signature = 'sikartap:apply-due-tetap';

    protected $description = 'Terapkan pengangkatan Karyawan Tetap Sikartap yang tanggal TMT-nya sudah tiba';

    public function handle(SikartapDirectorDecisionService $service): int
    {
        $result = $service->applyDueTetap();

        $this->info("Karyawan tetap diterapkan: {$result['applied']}, gagal: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
