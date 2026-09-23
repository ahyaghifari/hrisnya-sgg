<?php

namespace App\Console\Commands;

use App\Services\Sp\SpService;
use Illuminate\Console\Command;

/**
 * Job harian (planning/2026-09-23-sp-dan-merit-point.md §3.5) — user_hr_balances.active_sp_level
 * bisa basi murni karena employee_sps.end_date lewat tanpa event apa pun. Dijadwalkan harian di
 * bootstrap/app.php. Refresh eager saat SP baru disetujui (SpService::afterIssued) tetap jalan
 * terpisah, job ini cuma jaring pengaman buat yang jadi expired karena waktu lewat.
 */
class RefreshSpBalances extends Command
{
    protected $signature = 'sp:refresh-balances';

    protected $description = 'Refresh cache user_hr_balances.active_sp_level untuk user yang SP-nya mungkin sudah expired';

    public function handle(SpService $service): int
    {
        $count = $service->refreshExpiredBalances();

        $this->info("user_hr_balances direfresh untuk {$count} user.");

        return self::SUCCESS;
    }
}
