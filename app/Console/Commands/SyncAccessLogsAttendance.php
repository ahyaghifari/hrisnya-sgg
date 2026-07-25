<?php

namespace App\Console\Commands;

use App\Models\Attendance\SyncAccessLog;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use Illuminate\Console\Command;

/**
 * Dijalankan tiap menit lewat scheduler (lihat bootstrap/app.php), langsung lewat cron - bukan
 * queue. Incremental - cuma baca access_logs yang lebih baru dari watermark
 * SyncAccessLog::watermark(), bukan scan ulang semua baris hari itu tiap kali dipanggil (lihat
 * AccessLogScheduleReader::readAndGroupIncremental).
 */
class SyncAccessLogsAttendance extends Command
{
    protected $signature = 'attendance:sync-access-logs';

    protected $description = 'Sinkron incremental access_logs -> attendance_records berdasarkan watermark terakhir';

    public function handle(
        AccessLogScheduleReader $reader,
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        AttendanceRecordRepository $repo,
    ): int {
        $watermark = SyncAccessLog::watermark();

        $result = $reader->readAndGroupIncremental($watermark->last_synced_at?->toDateTimeString());

        if ($result['max_synced_at'] === null) {
            $this->info('Tidak ada access_logs baru sejak sync terakhir.');

            return Command::SUCCESS;
        }

        $fixedCount = 0;
        foreach ($fixedCalculator->calculate($result['buckets']['fixed']) as $r) {
            $repo->saveCalculatedResult($r, 'access_logs_fixed');
            $fixedCount++;
        }

        $flexibleCount = 0;
        foreach ($flexibleCalculator->calculate($result['buckets']['flexible']) as $r) {
            $repo->saveCalculatedResult($r, 'access_logs_flexible');
            $flexibleCount++;
        }

        $watermark->update(['last_synced_at' => $result['max_synced_at']]);

        $this->info("Sync selesai: {$fixedCount} fixed, {$flexibleCount} flexible disimpan. Watermark -> {$result['max_synced_at']}.");

        return Command::SUCCESS;
    }
}
