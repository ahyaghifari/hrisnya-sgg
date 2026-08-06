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
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Dijalankan tiap menit lewat scheduler (lihat bootstrap/app.php), langsung lewat cron - bukan
 * queue. Incremental - cuma baca access_logs yang lebih baru dari watermark
 * SyncAccessLog::watermark(), bukan scan ulang semua baris hari itu tiap kali dipanggil (lihat
 * AccessLogScheduleReader::readAndGroupIncremental).
 *
 * Log cuma ditulis ke channel `attendance_sync` (config/logging.php) kalau MEMANG ada access_logs
 * baru buat diproses - run kosong (mayoritas menit, tidak ada scan baru) sengaja tidak menulis
 * apapun ke file log, supaya isinya tetap padat/gampang ditelusuri.
 */
class SyncAccessLogsAttendance extends Command
{
    protected $signature = 'attendance:sync-access-logs';

    protected $description = 'Sinkron incremental access_logs -> attendance_records berdasarkan watermark terakhir';

    public function handle(
        AccessLogScheduleReader $reader,
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        ShiftScheduleCalculator $shiftCalculator,
        AttendanceRecordRepository $repo,
    ): int {
        $watermark = SyncAccessLog::watermark();
        $since = $watermark->last_synced_at?->toDateTimeString();

        $result = $reader->readAndGroupIncremental($since);

        if ($result['max_synced_at'] === null) {
            $this->info('Tidak ada access_logs baru sejak sync terakhir.');

            return Command::SUCCESS;
        }

        $log = Log::channel('attendance_sync');
        // runId cuma buat gampang grep 1 eksekusi utuh dari banyak baris log yang saling
        // berselang (beberapa run bisa overlap penulisan kalau durasinya kebetulan lewat 1 menit).
        $runId = (string) Str::ulid();

        $log->info("[{$runId}] access_logs baru terdeteksi. watermark_sebelumnya=" . ($since ?? '(belum pernah sync)'));

        $fixedCount = 0;
        foreach ($fixedCalculator->calculate($result['buckets']['fixed']) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_fixed');

            if (! empty($r['checkout_rejected'])) {
                $log->warning("[{$runId}] checkout ditolak (durasi kerja di bawah minimum): nip={$r['user']->nip} tanggal={$r['date']}");
            } else {
                $log->info("[{$runId}] diproses (fixed): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
            }

            $this->notifyCheckInOut($r, $record);
            $fixedCount++;
        }

        $flexibleCount = 0;
        foreach ($flexibleCalculator->calculate($result['buckets']['flexible']) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_flexible');
            $log->info("[{$runId}] diproses (flexible): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
            $this->notifyCheckInOut($r, $record);
            $flexibleCount++;
        }

        $shiftCount = 0;
        foreach ($shiftCalculator->calculate($result['buckets']['shift']) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_shift');
            $log->info("[{$runId}] diproses (shift): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
            $this->notifyCheckInOut($r, $record);
            $shiftCount++;
        }

        // Scan yang user-nya ketemu tapi jadwalnya TIDAK ketemu untuk tanggal itu - tidak
        // pernah masuk ke buckets manapun jadi tidak pernah tersimpan ke attendance_records.
        // Kabari karyawannya langsung supaya tidak diam-diam dianggap tidak absen.
        foreach ($result['unresolved_schedule'] as $entry) {
            $log->warning("[{$runId}] ditolak (jadwal tidak ditemukan): nip={$entry['employee_id']} tanggal={$entry['date']}");
            $entry['user']->notify(new AttendanceScanRejected(
                $entry['date'],
                'jadwal Anda tidak ditemukan untuk tanggal ini, hubungi HR/atasan Anda.'
            ));
        }

        // Scan dari device yang tidak terdaftar (atau terdaftar tapi beda company) - diblokir
        // total, tidak ikut dihitung/disimpan (lihat AccessLogScheduleReader::groupAndBucket).
        foreach ($result['rejected_device'] as $entry) {
            $devices = implode(', ', $entry['device_names']);
            $log->warning("[{$runId}] ditolak (device tidak dikenal): nip={$entry['employee_id']} tanggal={$entry['date']} device={$devices}");
            $entry['user']->notify(new AttendanceScanRejected(
                $entry['date'],
                'device absensi yang Anda pakai tidak terdaftar untuk perusahaan Anda, hubungi HR/admin.'
            ));
        }

        // employee_id di access_logs yang sama sekali tidak match users.nip manapun - tidak ada
        // User buat dikabari, jadi ini satu-satunya jejaknya.
        foreach ($result['unmatched_users'] as $employeeId => $scanCount) {
            $log->warning("[{$runId}] dilewati (NIP tidak dikenal di sistem): employee_id={$employeeId} jumlah_scan={$scanCount}");
        }

        $watermark->update(['last_synced_at' => $result['max_synced_at']]);

        $summary = "[{$runId}] selesai: fixed={$fixedCount} flexible={$flexibleCount} shift={$shiftCount} "
            . 'jadwal_tidak_ditemukan=' . count($result['unresolved_schedule']) . ' '
            . 'device_tidak_dikenal=' . count($result['rejected_device']) . ' '
            . 'nip_tidak_dikenal=' . count($result['unmatched_users']) . ' '
            . "watermark_baru={$result['max_synced_at']}";
        $log->info($summary);

        $this->info("Sync selesai: {$fixedCount} fixed, {$flexibleCount} flexible, {$shiftCount} shift disimpan. Watermark -> {$result['max_synced_at']}.");

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

        // checkout_rejected (FixedScheduleCalculator) => check_out_time TIDAK ikut tersimpan
        // biarpun is_new_checkout true (dihitung reader SEBELUM calculator menolaknya) - jangan
        // kirim notifikasi checkout yang datanya sendiri tidak benar-benar tersimpan, kabari
        // pakai AttendanceScanRejected supaya karyawan tahu kenapa checkout-nya tidak tercatat.
        if ($r['is_new_checkout'] && empty($r['checkout_rejected'])) {
            $r['user']->notify(new AttendanceCheckedOut($record));
        } elseif ($r['is_new_checkout'] && ! empty($r['checkout_rejected'])) {
            $r['user']->notify(new AttendanceScanRejected(
                $r['date'],
                "durasi kerja Anda belum memenuhi minimum ({$r['config']->min_work_minutes} menit), silakan scan pulang lagi setelah durasi kerja tercukupi."
            ));
        }
    }
}
