<?php

namespace App\Console\Commands;

use App\Services\Task\TaskService;
use Illuminate\Console\Command;

/**
 * Aktifkan lagi tugas Board berulang yang periodenya sudah lewat (status Selesai -> Todo, tenggat
 * berikutnya, checklist di-unceklis). Dijadwalkan harian di bootstrap/app.php.
 */
class ResumeRecurringTasks extends Command
{
    protected $signature = 'tasks:resume-recurring';

    protected $description = 'Aktifkan lagi tugas berulang yang periodenya sudah lewat';

    public function handle(TaskService $service): int
    {
        $this->info('Tugas diaktifkan lagi: ' . $service->resumeDueRecurring());

        return self::SUCCESS;
    }
}
