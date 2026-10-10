<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\User;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use Carbon\Carbon;

/**
 * Sync ulang presensi 1 karyawan untuk 1 rentang tanggal. Dipakai Rekap Presensi (admin,
 * per baris) dan portal karyawan (sync sendiri: kartu Presensi Hari Ini + Profil Saya).
 */
class AttendanceResyncService
{
    /**
     * Dua tahap, sama seperti tombol massal tapi untuk 1 karyawan: (1) baca ulang scan access_logs
     * di rentang itu lalu hitung & simpan lewat calculator jadwalnya (sama dengan runSync() Rekap,
     * tanpa notifikasi push karena ini koreksi manual, bukan presensi baru); (2) hitung ulang jam
     * kerja, telat, dan pulang cepat baris yang punya jam masuk & pulang (mencakup baris yang tidak
     * punya scan mentah, mis. dari aplikasi mobile).
     *
     * @return array{logs: int, recalculated: int, skipped: int, no_nip: bool}
     */
    public function resyncUserRange(User $user, string $from, string $to): array
    {
        $counts = ['logs' => 0, 'recalculated' => 0, 'skipped' => 0, 'no_nip' => ! filled($user->nip)];

        if (! $counts['no_nip']) {
            $reader = app(AccessLogScheduleReader::class);

            // Pairing overnight berantai (scan pertama hari ini "dipinjam" jadi pulang shift semalam),
            // jadi rentang baca harus mulai dari awal rantai & lebihi 1 hari ke depan - kalau tidak,
            // jadwal lewat tengah malam berturut2 salah pasang / pulang kosong. Hasil di luar
            // [$from, $to] dibuang.
            // ponytail: mundur maks 31 hari, cukup untuk rantai overnight realistis.
            $readFrom = Carbon::parse($from);
            for ($i = 0; $i < 31 && $reader->isOvernightShiftDay($user, $readFrom->copy()->subDay()->toDateString()); $i++) {
                $readFrom->subDay();
            }
            $readTo = Carbon::parse($to)->addDay()->toDateString();

            $result = $reader->readAndGroup($readFrom->toDateString(), $readTo, $user->nip);

            $producedByDate = [];
            foreach ($result['buckets'] as $entries) {
                foreach ($entries as $e) {
                    $producedByDate[$e['date']][] = $e;
                }
            }

            foreach ($result['buckets'] as $type => $entries) {
                $result['buckets'][$type] = array_filter($entries, fn ($e) => $e['date'] >= $from && $e['date'] <= $to);
            }
            $repo = app(AttendanceRecordRepository::class);
            $calculators = [
                'fixed' => app(FixedScheduleCalculator::class),
                'flexible' => app(FlexibleScheduleCalculator::class),
                'shift' => app(ShiftScheduleCalculator::class),
            ];

            foreach ($calculators as $type => $calculator) {
                foreach ($calculator->calculate($result['buckets'][$type]) as $r) {
                    if ($r['user']->id !== $user->id) {
                        continue;
                    }

                    $repo->saveCalculatedResult($r, 'access_logs_' . $type);
                    $counts['logs']++;
                }
            }

            $counts['logs'] += \App\Models\Attendance\OvertimeAttendanceRecord::saveEntries(
                array_filter($result['buckets']['overtime'], fn ($e) => $e['user']->id === $user->id),
                'access_logs'
            );

            foreach ($result['buckets']['extra_shift'] as $entry) {
                if ($entry['user']->id === $user->id) {
                    $this->saveExtraShiftEntry($entry, $calculators, $repo) && $counts['logs']++;
                }
            }

            $this->dropBorrowedScanRecords($user, $from, $to, $producedByDate);
        }

        $records = AttendanceRecords::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('check_in_time')
            ->whereNotNull('check_out_time')
            ->with('user')
            ->get();

        foreach ($records as $record) {
            $this->recalculateRecord($record) ? $counts['recalculated']++ : $counts['skipped']++;
        }

        return $counts;
    }

    /**
     * Buang baris auto-hitung yang jam masuknya ternyata scan PULANG shift lewat tengah malam
     * semalam (sisa sync lama yang salah baca scan itu sebagai masuk hari ini). Hitung ulang
     * hanya menulis baris, tidak pernah menghapus, jadi baris basi itu bertahan. Syarat sempit:
     * sumber access_logs_*, tanggal itu tidak menghasilkan entri sama sekali, dan jam masuknya
     * persis sama dengan jam pulang entri kemarin.
     *
     * @param  array<string, array<int, array>>  $producedByDate
     */
    private function dropBorrowedScanRecords(User $user, string $from, string $to, array $producedByDate): void
    {
        AttendanceRecords::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->where('source', 'like', 'access_logs_%')
            ->whereNotNull('check_in_time')
            ->get()
            ->each(function (AttendanceRecords $record) use ($producedByDate) {
                $date = Carbon::parse($record->date);

                if (isset($producedByDate[$date->toDateString()])) {
                    return;
                }

                foreach ($producedByDate[$date->copy()->subDay()->toDateString()] ?? [] as $prev) {
                    if (($prev['check_out'] ?? null) === $record->check_in_time) {
                        $record->delete();

                        return;
                    }
                }
            });
    }

    /**
     * check_in entry bucket 'extra_shift' beneran waktu shift extra-nya (>= jam mulai shift extra
     * dikurangi buffer check-in-nya sendiri) - kalau tidak, check_in yang kebawa sebenarnya jam
     * masuk shift NORMAL. Dipakai bareng SyncAccessLogsAttendance (cron) - satu aturan.
     */
    public static function isGenuineExtraShiftScan(array $entry): bool
    {
        // Pulang saja (check_in kosong, lupa scan masuk): pairDay() sudah memutuskan pemiliknya
        // extra shift - entri milik shift normal membawa extra_shift_handled dan tidak sampai sini.
        if (! $entry['check_in']) {
            return filled($entry['check_out'] ?? null);
        }

        $earliestCheckIn = Carbon::createFromFormat('H:i:s', $entry['config']->start_time)
            ->subMinutes($entry['config']->checkin_buffer_minutes);

        return Carbon::createFromFormat('H:i:s', $entry['check_in'])->gte($earliestCheckIn);
    }

    /**
     * Simpan 1 entry bucket 'extra_shift' - sama dengan SyncAccessLogsAttendance: valid = simpan ke
     * ExtraShiftAttendanceRecord; tidak valid = fallback diproses sebagai jadwal normal hari itu
     * (attendance_records). Tanpa notifikasi (koreksi manual). False = tidak ada yang tersimpan
     * (jadwal normal tidak ketemu/libur).
     *
     * @param  array<string, object>  $calculators  keyed fixed/flexible/shift
     */
    private function saveExtraShiftEntry(array $entry, array $calculators, AttendanceRecordRepository $repo): bool
    {
        if (self::isGenuineExtraShiftScan($entry)) {
            $r = $calculators['shift']->calculate([$entry])[0];
            ExtraShiftAttendanceRecord::saveCalculatedResult($r, $r['extra_shift_assignment_id'], 'access_logs_shift');

            return true;
        }

        $resolved = app(ScheduleResolver::class)->resolveForUserDate($entry['user'], $entry['date'], ignoreEffectiveDate: true);

        if (! $resolved || ($resolved['is_off_day'] ?? false)) {
            return false;
        }

        $r = $calculators[$resolved['type']]->calculate([array_merge($entry, $resolved)])[0];
        $repo->saveCalculatedResult($r, "access_logs_{$resolved['type']}");

        return true;
    }

    /** Hitung ulang 1 baris pakai rumus jadwal aslinya; false = dilewati (jadwal tak ketemu/libur/di bawah minimum). */
    private function recalculateRecord(AttendanceRecords $record): bool
    {
        if (! $record->user) {
            return false;
        }

        $resolved = app(ScheduleResolver::class)->resolveForUserDate($record->user, $record->date);

        if (! $resolved || ($resolved['is_off_day'] ?? false) || ! isset($resolved['config'])) {
            return false;
        }

        $entry = [
            'check_in' => $record->check_in_time,
            'check_out' => $record->check_out_time,
            'config' => $resolved['config'],
            'schedule' => $resolved['schedule'] ?? null,
        ];

        $result = match ($resolved['type']) {
            'fixed' => app(FixedScheduleCalculator::class)->calculate([$entry])[0] ?? null,
            'flexible' => app(FlexibleScheduleCalculator::class)->calculate([$entry])[0] ?? null,
            'shift' => app(ShiftScheduleCalculator::class)->calculate([$entry])[0] ?? null,
            default => null,
        };

        if (! $result || $result['working_minutes'] === null) {
            return false;
        }

        $record->update([
            'working_minutes' => $result['working_minutes'],
            'late_minutes' => $result['late_minutes'] ?? 0,
            'early_leave_minutes' => $result['early_leave_minutes'] ?? 0,
            'status' => $result['status'] ?? $record->status,
        ]);

        return true;
    }
}
