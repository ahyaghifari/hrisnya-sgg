<?php

namespace App\Console\Commands;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\SyncAccessLog;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use App\Notifications\AttendanceScanRejected;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
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

    /**
     * Jendela mundur watermark (menit). access_logs TIDAK punya id/created_at (lihat migration-nya),
     * jadi tidak ada cara tahu urutan MASUKNYA baris - yang ada cuma access_datetime (jam scan di
     * device). Baris yang baru masuk ke database SESUDAH watermark lewat jam scan-nya (device
     * dorong data belakangan / jaringan telat / importer batch) dengan watermark "> last_synced_at"
     * polos TIDAK AKAN PERNAH kebaca lagi - efeknya sebagian orang tidak pernah masuk
     * attendance_records sama sekali (dilaporkan user 2026-09-22, perusahaan holding).
     *
     * Dengan jendela ini, tiap run membaca ulang scan beberapa menit ke belakang. Aman karena
     * penyimpanannya idempoten: updateOrCreate per (nip,tanggal), check_in pakai min & check_out
     * pakai max, dan is_new_checkin/is_new_checkout otomatis false begitu barisnya sudah ada -
     * jadi tidak ada notifikasi dobel buat scan yang sudah pernah diproses.
     */
    private const WATERMARK_LOOKBACK_MINUTES = 15;

    public function handle(
        AccessLogScheduleReader $reader,
        ScheduleResolver $scheduleResolver,
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        ShiftScheduleCalculator $shiftCalculator,
        AttendanceRecordRepository $repo,
    ): int {
        $watermark = SyncAccessLog::watermark();

        // Watermark asli (buat nentuin mana entri yang BENAR-BENAR baru) vs titik baca yang
        // dimundurkan (buat nangkap baris yang telat masuk ke access_logs) - lihat konstanta di atas.
        $previousWatermark = $watermark->last_synced_at?->toDateTimeString();
        $since = $watermark->last_synced_at?->copy()->subMinutes(self::WATERMARK_LOOKBACK_MINUTES)->toDateTimeString();

        $result = $reader->readAndGroupIncremental($since);

        if ($result['max_synced_at'] === null) {
            $this->info('Tidak ada access_logs baru sejak sync terakhir.');

            return Command::SUCCESS;
        }

        $log = Log::channel('attendance_sync');
        // runId cuma buat gampang grep 1 eksekusi utuh dari banyak baris log yang saling
        // berselang (beberapa run bisa overlap penulisan kalau durasinya kebetulan lewat 1 menit).
        $runId = (string) Str::ulid();

        $log->info("[{$runId}] access_logs baru terdeteksi. watermark_sebelumnya=" . ($previousWatermark ?? '(belum pernah sync)') . " dibaca_sejak=" . ($since ?? '(semua)'));

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

            if (! empty($r['checkout_rejected'])) {
                $log->warning("[{$runId}] checkout ditolak (durasi kerja di bawah minimum): nip={$r['user']->nip} tanggal={$r['date']}");
            } else {
                $log->info("[{$runId}] diproses (flexible): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
            }

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

        // Hari yang ada extra shift approved - dilewati dari fixed/flexible/shift di atas
        // (lihat AccessLogScheduleReader::groupAndBucket), config-nya shift_type extra shift.
        // Divalidasi dulu (isGenuineExtraShiftScan()) sebelum dihitung: reader cuma tau ADA
        // extra shift hari itu, belum tau apakah pasangan check-in/check-out yang kepasangkan
        // beneran waktu extra shift-nya atau ternyata masih sisa scan shift normal (mis. scan
        // 4x sehari - masuk normal, pulang normal, masuk extra, pulang extra - tapi
        // pairScansForEmployee() cuma nyisain scan pertama & terakhir, jadi check_in yang
        // kebawa ke sini bisa jadi jam masuk shift NORMAL, bukan extra). Kalau tidak valid,
        // dicek ulang lewat ScheduleResolver dan diproses sebagai jadwal normal biasa -
        // nggak masalah, itu justru fallback yang benar.
        $extraShiftCount = 0;
        foreach ($result['buckets']['extra_shift'] as $entry) {
            if (! $this->isGenuineExtraShiftScan($entry)) {
                $this->reprocessAsNormalSchedule($entry, $scheduleResolver, $fixedCalculator, $flexibleCalculator, $shiftCalculator, $repo, $log, $runId);

                continue;
            }

            $r = $shiftCalculator->calculate([$entry])[0];
            ExtraShiftAttendanceRecord::saveCalculatedResult($r, $r['extra_shift_assignment_id'], 'access_logs_shift');
            $log->info("[{$runId}] diproses (extra_shift): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
            $extraShiftCount++;
        }

        // Scan yang user-nya ketemu tapi jadwalnya TIDAK ketemu untuk tanggal itu - tidak
        // pernah masuk ke buckets manapun jadi tidak pernah tersimpan ke attendance_records.
        // Kabari karyawannya langsung supaya tidak diam-diam dianggap tidak absen.
        foreach ($result['unresolved_schedule'] as $entry) {
            $log->warning("[{$runId}] ditolak (jadwal tidak ditemukan): nip={$entry['employee_id']} tanggal={$entry['date']}");

            // Cuma kabari kalau scan-nya memang baru - entri yang kebaca ulang lewat jendela mundur
            // watermark sudah pernah dikabari di run sebelumnya (lihat WATERMARK_LOOKBACK_MINUTES).
            if ($this->isNewSinceWatermark($entry, $previousWatermark)) {
                $entry['user']->notify(new AttendanceScanRejected(
                    $entry['date'],
                    'jadwal Anda tidak ditemukan untuk tanggal ini, hubungi HR/atasan Anda.'
                ));
            }
        }

        // Scan dari device yang tidak terdaftar (atau terdaftar tapi beda company) - diblokir
        // total, tidak ikut dihitung/disimpan (lihat AccessLogScheduleReader::groupAndBucket).
        foreach ($result['rejected_device'] as $entry) {
            $devices = implode(', ', $entry['device_names']);
            $log->warning("[{$runId}] ditolak (device tidak dikenal): nip={$entry['employee_id']} tanggal={$entry['date']} device={$devices}");

            if ($this->isNewSinceWatermark($entry, $previousWatermark)) {
                $entry['user']->notify(new AttendanceScanRejected(
                    $entry['date'],
                    'device absensi yang Anda pakai tidak terdaftar untuk perusahaan Anda, hubungi HR/admin.'
                ));
            }
        }

        // employee_id di access_logs yang sama sekali tidak match users.nip manapun - tidak ada
        // User buat dikabari, jadi ini satu-satunya jejaknya.
        foreach ($result['unmatched_users'] as $employeeId => $scanCount) {
            $log->warning("[{$runId}] dilewati (NIP tidak dikenal di sistem): employee_id={$employeeId} jumlah_scan={$scanCount}");
        }

        // Watermark cuma boleh MAJU. Dengan jendela mundur di atas, scan yang telat masuk bisa
        // bikin max_synced_at lebih tua dari watermark sekarang - kalau ditulis apa adanya,
        // watermark-nya mundur dan run berikutnya baca ulang makin jauh ke belakang terus.
        $newWatermark = max($result['max_synced_at'], $previousWatermark ?? $result['max_synced_at']);
        $watermark->update(['last_synced_at' => $newWatermark]);

        $summary = "[{$runId}] selesai: fixed={$fixedCount} flexible={$flexibleCount} shift={$shiftCount} extra_shift={$extraShiftCount} "
            . 'jadwal_tidak_ditemukan=' . count($result['unresolved_schedule']) . ' '
            . 'device_tidak_dikenal=' . count($result['rejected_device']) . ' '
            . 'nip_tidak_dikenal=' . count($result['unmatched_users']) . ' '
            . "watermark_baru={$newWatermark}";
        $log->info($summary);

        $this->info("Sync selesai: {$fixedCount} fixed, {$flexibleCount} flexible, {$shiftCount} shift, {$extraShiftCount} extra_shift disimpan. Watermark -> {$result['max_synced_at']}.");

        return Command::SUCCESS;
    }

    /**
     * Entri ini scan-nya benar-benar baru (belum pernah kebaca run sebelumnya)? Dipakai buat entri
     * yang DITOLAK (jadwal/device) - entri yang sukses tidak butuh ini karena is_new_checkin/
     * is_new_checkout sudah otomatis false begitu barisnya ada di attendance_records.
     */
    private function isNewSinceWatermark(array $entry, ?string $previousWatermark): bool
    {
        if ($previousWatermark === null) {
            return true;
        }

        $latestScan = $entry['check_out'] ?? $entry['check_in'] ?? null;

        if ($latestScan === null) {
            return true;
        }

        return "{$entry['date']} {$latestScan}" > $previousWatermark;
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

    /**
     * check_in entry bucket 'extra_shift' beneran waktu shift extra-nya (>= jam mulai shift
     * extra dikurangi buffer check-in-nya sendiri) - kalau enggak, itu tandanya check_in yang
     * kebawa ke sini sebenarnya jam masuk shift NORMAL (lihat komentar di handle()).
     */
    private function isGenuineExtraShiftScan(array $entry): bool
    {
        if (! $entry['check_in']) {
            return false;
        }

        $earliestCheckIn = Carbon::createFromFormat('H:i:s', $entry['config']->start_time)
            ->subMinutes($entry['config']->checkin_buffer_minutes);

        return Carbon::createFromFormat('H:i:s', $entry['check_in'])->gte($earliestCheckIn);
    }

    /**
     * Fallback buat entry bucket 'extra_shift' yang ternyata tidak valid (isGenuineExtraShiftScan
     * gagal) - cek ulang jadwal NORMAL-nya lewat ScheduleResolver dan proses seperti hari tanpa
     * extra shift sama sekali (fixed/flexible/shift, tersimpan ke attendance_records biasa).
     */
    private function reprocessAsNormalSchedule(
        array $entry,
        ScheduleResolver $scheduleResolver,
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        ShiftScheduleCalculator $shiftCalculator,
        AttendanceRecordRepository $repo,
        $log,
        string $runId,
    ): void {
        $resolved = $scheduleResolver->resolveForUserDate($entry['user'], $entry['date'], ignoreEffectiveDate: true);

        if (! $resolved || ($resolved['is_off_day'] ?? false)) {
            $log->warning("[{$runId}] ditolak (jadwal tidak ditemukan setelah extra shift tidak valid): nip={$entry['employee_id']} tanggal={$entry['date']}");
            $entry['user']->notify(new AttendanceScanRejected(
                $entry['date'],
                'jadwal Anda tidak ditemukan untuk tanggal ini, hubungi HR/atasan Anda.'
            ));

            return;
        }

        $entry = array_merge($entry, $resolved);
        $calculator = match ($resolved['type']) {
            'fixed' => $fixedCalculator,
            'flexible' => $flexibleCalculator,
            'shift' => $shiftCalculator,
        };

        $r = $calculator->calculate([$entry])[0];
        $record = $repo->saveCalculatedResult($r, "access_logs_{$resolved['type']}");
        $log->info("[{$runId}] diproses ({$resolved['type']}, fallback dari extra_shift tidak valid): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
        $this->notifyCheckInOut($r, $record);
    }
}
