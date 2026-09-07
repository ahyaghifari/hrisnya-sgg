<?php

namespace App\Console\Commands;

use App\Services\User\UserTransitionService;
use Illuminate\Console\Command;

/**
 * Terapkan efek transisi (Promosi/Demosi/Mutasi/PHK) yang sudah disetujui begitu tanggal
 * efektifnya tiba. Dijadwalkan harian di bootstrap/app.php. Transisi yang tanggal efektifnya
 * hari approve <= hari ini sudah langsung diterapkan saat approve - yang diproses di sini cuma
 * yang ditunda (approved, applied_at NULL, effective_date <= hari ini).
 */
class ApplyDueUserTransitions extends Command
{
    protected $signature = 'transitions:apply-due';

    protected $description = 'Terapkan transisi karyawan yang sudah disetujui & tanggal efektifnya tiba';

    public function handle(UserTransitionService $service): int
    {
        $result = $service->applyDueTransitions();

        $this->info("Transisi diterapkan: {$result['applied']}, gagal: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
