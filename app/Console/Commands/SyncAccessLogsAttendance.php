<?php

namespace App\Console\Commands;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\OvertimeAttendanceRecord;
use App\Models\Attendance\SyncAccessLog;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Notifications\AttendanceCheckedIn;
use App\Notifications\AttendanceCheckedOut;
use App\Notifications\AttendanceScanRejected;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
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
     * Watermark cuma menentukan (nip, tanggal) mana yang tersentuh scan baru; hari itu dihitung
     * ulang PENUH dari semua scan-nya lewat jalur pairing yang sama dengan tombol Sync Presensi
     * (lihat AccessLogScheduleReader::readAndGroupIncremental) - tidak ada keputusan yang
     * bergantung pada "scan mana yang baru", jadi aman dibaca ulang. Scan yang telat masuk
     * access_logs (jamnya lebih tua dari watermark) tetap tidak terbaca di sini - itu tugas
     * SweepMissedAccessLogs.
     */
    public function handle(
        AccessLogScheduleReader $reader,
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        ShiftScheduleCalculator $shiftCalculator,
        AttendanceRecordRepository $repo,
    ): int {
        $watermark = SyncAccessLog::watermark();

        $previousWatermark = $watermark->last_synced_at?->toDateTimeString();

        $result = $reader->readAndGroupIncremental($previousWatermark);

        if ($result['max_synced_at'] === null) {
            $this->info('Tidak ada access_logs baru sejak sync terakhir.');

            return Command::SUCCESS;
        }

        $log = Log::channel('attendance_sync');
        // runId cuma buat gampang grep 1 eksekusi utuh dari banyak baris log yang saling
        // berselang (beberapa run bisa overlap penulisan kalau durasinya kebetulan lewat 1 menit).
        $runId = (string) Str::ulid();

        $log->info("[{$runId}] access_logs baru terdeteksi. watermark_sebelumnya=" . ($previousWatermark ?? '(belum pernah sync)'));

        $fixedCount = 0;
        foreach ($fixedCalculator->calculate($result['buckets']['fixed']) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_fixed');

            if (! empty($r['checkout_rejected'])) {
                $log->warning("[{$runId}] checkout ditolak (durasi kerja di bawah minimum): nip={$r['user']->nip} tanggal={$r['date']}");
            } else {
                $log->info("[{$runId}] diproses (fixed): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
            }

            $this->notifyCheckInOut($r, $record, 'fixed');
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

            $this->notifyCheckInOut($r, $record, 'flexible');
            $flexibleCount++;
        }

        $shiftCount = 0;
        foreach ($shiftCalculator->calculate($result['buckets']['shift']) as $r) {
            $record = $repo->saveCalculatedResult($r, 'access_logs_shift');
            $log->info("[{$runId}] diproses (shift): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));
            $this->notifyCheckInOut($r, $record, 'shift');
            $shiftCount++;
        }

        // Scan di hari libur jadwal yang ada pengajuan lembur - bukti lembur, bukan masuk/telat.
        $overtimeCount = OvertimeAttendanceRecord::saveEntries($result['buckets']['overtime'], 'access_logs');
        if ($overtimeCount > 0) {
            $log->info("[{$runId}] diproses (lembur hari libur): {$overtimeCount} scan");
        }

        // Pasangan extra shift - sudah diputuskan pemiliknya oleh ShiftAttendanceReader (scan yang
        // ternyata shift normal tidak sampai sini, sudah masuk bucket normal), langsung dihitung.
        $extraShiftCount = 0;
        foreach ($result['buckets']['extra_shift'] as $entry) {
            $r = $shiftCalculator->calculate([$entry])[0];
            $existing = ExtraShiftAttendanceRecord::where('extra_shift_assignment_id', $r['extra_shift_assignment_id'])->first();
            $saved = ExtraShiftAttendanceRecord::saveCalculatedResult($r, $r['extra_shift_assignment_id'], 'access_logs_shift');
            $log->info("[{$runId}] diproses (extra_shift): nip={$r['user']->nip} nama={$r['user']->name} tanggal={$r['date']} status={$r['status']} checkin={$r['check_in']} checkout=" . ($r['check_out'] ?? '-'));

            // Notifikasi sekali per event, sama seperti shift normal (dibanding baris extra yang sudah ada).
            $context = $this->messageContext($r, 'extra') + [
                'category' => ExtraShiftAssignment::find($r['extra_shift_assignment_id'])?->category,
            ];
            if (filled($r['check_in']) && blank($existing?->check_in_time)) {
                $r['user']->notify(new AttendanceCheckedIn($saved, $context));
            }
            if (filled($r['check_out']) && blank($existing?->check_out_time)) {
                $r['user']->notify(new AttendanceCheckedOut($saved, $context));
            }
            $extraShiftCount++;
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

        // Scan dari device yang tidak terdaftar sama sekali - diblokir total, tidak ikut
        // dihitung/disimpan (lihat AccessLogScanReader::splitRejectedDevices).
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

        // Watermark cuma boleh MAJU (pengaman - watermark mundur bikin run berikutnya menghitung
        // ulang hari yang sama dan mengirim ulang notifikasi).
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
     * @param  array{user: \App\Models\User, is_new_checkin: bool, is_new_checkout: bool}  $r
     * @param  string  $kind  fixed | flexible | shift (menentukan gaya pesan)
     */
    private function notifyCheckInOut(array $r, AttendanceRecords $record, string $kind): void
    {
        $context = $this->messageContext($r, $kind);

        // Pakai $r['user'] (sudah di-load AccessLogScheduleReader::resolveUsersForRows()) bukan
        // $record->user, supaya tidak query ulang lewat relasi cuma buat notify.
        if ($r['is_new_checkin']) {
            $r['user']->notify(new AttendanceCheckedIn($record, $context));
        }

        // checkout_rejected (FixedScheduleCalculator) => check_out_time TIDAK ikut tersimpan
        // biarpun is_new_checkout true (dihitung reader SEBELUM calculator menolaknya) - jangan
        // kirim notifikasi checkout yang datanya sendiri tidak benar-benar tersimpan, kabari
        // pakai AttendanceScanRejected supaya karyawan tahu kenapa checkout-nya tidak tercatat.
        if ($r['is_new_checkout'] && empty($r['checkout_rejected'])) {
            $r['user']->notify(new AttendanceCheckedOut($record, $context));
        } elseif ($r['is_new_checkout'] && ! empty($r['checkout_rejected'])) {
            $r['user']->notify(new AttendanceScanRejected(
                $r['date'],
                "durasi kerja Anda belum memenuhi minimum ({$r['config']->min_work_minutes} menit), silakan scan pulang lagi setelah durasi kerja tercukupi."
            ));
        }
    }

    /**
     * Bahan pesan notifikasi (lihat AttendanceMessageComposer). Izin hanya dicari kalau memang
     * relevan (terlambat / pulang lebih awal), supaya hari normal tidak menambah query.
     *
     * @return array<string, mixed>
     */
    private function messageContext(array $r, string $kind): array
    {
        $config = $r['config'];
        $late = (int) ($r['late_minutes'] ?? 0);
        $earlyLeave = (int) ($r['early_leave_minutes'] ?? 0);

        $context = [
            'kind' => $kind,
            'date' => $r['date'],
            'late_minutes' => $late,
            'early_leave_minutes' => $earlyLeave,
        ];

        if (in_array($kind, ['shift', 'extra'], true)) {
            $context['shift_label'] = $config->name ?? null;
        }

        // Pengingat Corporate Value: karyawan holding, masuk hari Senin (composer menambahkannya
        // hanya kalau masuknya tidak terlambat).
        $context['corporate_value_reminder'] = $kind !== 'extra'
            && Carbon::parse($r['date'])->isMonday()
            && $r['user']->isFromHoldingCompany();

        if (in_array($kind, ['fixed', 'shift'], true) && ! empty($r['check_in']) && ! empty($config->start_time)) {
            $checkIn = Carbon::createFromFormat('H:i:s', $r['check_in']);
            $start = Carbon::createFromFormat('H:i:s', $config->start_time);
            $context['early_arrival_minutes'] = max(0, (int) $checkIn->diffInMinutes($start));
        }

        if ($kind === 'flexible') {
            $context['meets_target'] = $r['meets_target'] ?? null;
            $context['target_minutes'] = (int) ($config->max_work_minutes ?: $config->min_work_minutes ?: 0);
        }

        if ($kind !== 'extra' && $kind !== 'flexible') {
            $context['permitted_late'] = $late > 0 ? $this->hasApprovedPermission($r, ['terlambat']) : null;
            $context['permitted_early_leave'] = $earlyLeave > 0 ? $this->hasApprovedPermission($r, ['pulang_cepat', 'meninggalkan_jam_kerja']) : null;
        }

        return $context;
    }

    /** @param  array<int, string>  $types  permission_type yang dianggap izin untuk keadaan itu */
    private function hasApprovedPermission(array $r, array $types): bool
    {
        return AttendanceRequestPermissionDetails::query()
            ->whereDate('date', $r['date'])
            ->whereIn('permission_type', $types)
            ->whereHas('attendanceRequest', fn ($q) => $q
                ->where('user_id', $r['user']->id)
                ->where('type', 'permission')
                ->where('status', 'approved'))
            ->exists();
    }
}
