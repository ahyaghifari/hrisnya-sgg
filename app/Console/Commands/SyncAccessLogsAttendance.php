<?php

namespace App\Console\Commands;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\SyncAccessLog;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use App\Notifications\AttendanceScanRejected;
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
            $record = $repo->saveCalculatedResult($r, 'access_logs_fixed');
            $this->notifyCheckInOut($r, $record);
            $fixedCount++;
        }

        $flexibleCount = 0;
        foreach ($flexibleCalculator->calculate($result['buckets']['flexible']) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_flexible');
            $this->notifyCheckInOut($r, $record);
            $flexibleCount++;
        }

        // Scan yang user-nya ketemu tapi jadwalnya TIDAK ketemu untuk tanggal itu - tidak
        // pernah masuk ke buckets manapun jadi tidak pernah tersimpan ke attendance_records.
        // Kabari karyawannya langsung supaya tidak diam-diam dianggap tidak absen.
        foreach ($result['unresolved_schedule'] as $entry) {
            $entry['user']->notify(new AttendanceScanRejected(
                $entry['date'],
                'jadwal Anda tidak ditemukan untuk tanggal ini, hubungi HR/atasan Anda.'
            ));
        }

        // Scan dari device yang tidak terdaftar (atau terdaftar tapi beda company) - diblokir
        // total, tidak ikut dihitung/disimpan (lihat AccessLogScheduleReader::groupAndBucket).
        foreach ($result['rejected_device'] as $entry) {
            $entry['user']->notify(new AttendanceScanRejected(
                $entry['date'],
                'device absensi yang Anda pakai tidak terdaftar untuk perusahaan Anda, hubungi HR/admin.'
            ));
        }

        $watermark->update(['last_synced_at' => $result['max_synced_at']]);

        $this->info("Sync selesai: {$fixedCount} fixed, {$flexibleCount} flexible disimpan. Watermark -> {$result['max_synced_at']}.");

        return Command::SUCCESS;
    }

    /**
     * @param  array{user: \App\Models\User, is_new_checkin: bool, is_new_checkout: bool}  $r
     */
    private function notifyCheckInOut(array $r, AttendanceRecords $record): void
    {
        // Pakai $r['user'] (sudah di-load AccessLogScheduleReader::resolveUsers()) bukan
        // $record->user, supaya tidak query ulang lewat relasi cuma buat notify.
        if ($r['is_new_checkin']) {
            $r['user']->notify(new AttendanceCheckedIn($record));
        }

        if ($r['is_new_checkout']) {
            $r['user']->notify(new AttendanceCheckedOut($record));
        }
    }
}
