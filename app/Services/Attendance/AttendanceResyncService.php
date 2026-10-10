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
            // Rantai shift lewat tengah malam (scan konteks kemarin/besok) diambil sendiri oleh
            // ShiftAttendanceReader - di sini cukup rentang yang diminta.
            $result = app(AccessLogScheduleReader::class)->readAndGroup($from, $to, $user->nip);

            $producedByDate = [];
            foreach ($result['buckets'] as $entries) {
                foreach ($entries as $e) {
                    $producedByDate[$e['date']][] = $e;
                }
            }

            // Pulang saja kemarin (lupa scan masuk) hasil scan pertama $from milik $from-1 - ikut disimpan.
            $prevOfFrom = Carbon::parse($from)->subDay()->toDateString();
            foreach ($result['buckets'] as $type => $entries) {
                $result['buckets'][$type] = array_filter($entries, fn ($e) => ($e['date'] >= $from && $e['date'] <= $to)
                    || ($e['date'] === $prevOfFrom && array_key_exists('check_in', $e) && $e['check_in'] === null));
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
                    $r = $calculators['shift']->calculate([$entry])[0];
                    ExtraShiftAttendanceRecord::saveCalculatedResult($r, $r['extra_shift_assignment_id'], 'access_logs_shift');
                    $counts['logs']++;
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
