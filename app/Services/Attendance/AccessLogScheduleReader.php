<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 1-4 pipeline access_logs -> jadwal: baca access_logs, cari user (batch), cari jadwal
 * per (user, tanggal) lewat ScheduleResolver, lalu kelompokkan per tipe jadwal (fixed/flexible/
 * shift) supaya masing2 diserahkan ke calculator-nya sendiri (lihat
 * App\Services\Attendance\Calculators\*).
 *
 * ponytail: effective_from/effective_until pada ScheduleAssignment sengaja DIABAIKAN di sini
 * (lihat parameter $ignoreEffectiveDate di ScheduleResolver) - keputusan eksplisit user untuk
 * tahap eksperimen ini, supaya jadwal contoh yang baru dibuat tetap ke-resolve ke tanggal lama
 * di access_logs. Kode real-time (UI) TIDAK bypass ini, lihat ResolvesTodayAttendanceStatus.
 */
class AccessLogScheduleReader
{
    public function __construct(private ScheduleResolver $scheduleResolver) {}

    /**
     * @return array{
     *     buckets: array{fixed: array, flexible: array, shift: array, off: array},
     *     unmatched_users: array<string,int>,
     *     unresolved_schedule: array,
     * }
     */
    public function readAndGroup(string $fromDate, string $toDate, ?string $nip = null): array
    {
        return $this->groupAndBucket($this->readAndGroupScans($fromDate, $toDate, $nip));
    }

    /**
     * Versi incremental - cuma baca access_logs yang access_datetime-nya lebih baru dari
     * $sinceDatetime (dipakai App\Console\Commands\SyncAccessLogsAttendance tiap menit, lihat
     * App\Models\Attendance\SyncAccessLog buat watermark-nya), bukan scan ulang seluruh hari
     * setiap kali dipanggil. Baris check_in/check_out yang sudah tercatat di attendance_records
     * dari batch sebelumnya di-merge (min buat check_in, max buat check_out) supaya scan baru
     * yang notabene check-out tidak menimpa check_in yang sudah benar dari batch sebelumnya.
     *
     * @return array{
     *     buckets: array{fixed: array, flexible: array, shift: array, off: array},
     *     unmatched_users: array<string,int>,
     *     unresolved_schedule: array,
     *     max_synced_at: ?string,
     * }
     */
    public function readAndGroupIncremental(?string $sinceDatetime): array
    {
        [$rawEntries, $maxSyncedAt] = $this->readAndGroupScansIncremental($sinceDatetime);

        return $this->groupAndBucket($rawEntries) + ['max_synced_at' => $maxSyncedAt];
    }

    private function groupAndBucket(array $rawEntries): array
    {
        [$withUser, $unmatchedUsers] = $this->resolveUsers($rawEntries);

        $buckets = ['fixed' => [], 'flexible' => [], 'shift' => [], 'off' => []];
        $unresolvedSchedule = [];

        foreach ($withUser as $entry) {
            $resolved = $this->scheduleResolver->resolveForUserDate($entry['user'], $entry['date'], ignoreEffectiveDate: true);

            if (! $resolved) {
                $unresolvedSchedule[] = $entry;
                continue;
            }

            $entry = array_merge($entry, $resolved);
            $buckets[$resolved['is_off_day'] ? 'off' : $resolved['type']][] = $entry;
        }

        return [
            'buckets' => $buckets,
            'unmatched_users' => $unmatchedUsers,
            'unresolved_schedule' => $unresolvedSchedule,
        ];
    }

    /**
     * Tahap 1: ambil access_logs, kelompokkan per employee_id + tanggal.
     * Scan pertama = check-in, scan terakhir = check-out (kalau cuma 1 scan, check-out null).
     */
    private function readAndGroupScans(string $fromDate, string $toDate, ?string $nip): array
    {
        $query = DB::table('access_logs')->whereBetween('access_date', [$fromDate, $toDate]);
        if ($nip) {
            $query->where('employee_id', $nip);
        }

        $scanGroups = $query->orderBy('access_datetime')->get()
            ->groupBy(fn ($row) => $row->employee_id . '|' . $row->access_date);

        $entries = [];
        foreach ($scanGroups as $key => $rows) {
            [$employeeId, $date] = explode('|', $key, 2);
            $entries[] = [
                'employee_id' => $employeeId,
                'date'        => $date,
                'check_in'    => $rows->first()->access_time,
                'check_out'   => $rows->count() > 1 ? $rows->last()->access_time : null,
                'scan_count'  => $rows->count(),
            ];
        }

        return $entries;
    }

    /**
     * Versi incremental Tahap 1 - cuma ambil baris access_logs baru (access_datetime > since),
     * lalu merge check_in/check_out-nya dengan attendance_records yang sudah ada (kalau ada)
     * biar check_in dari batch sebelumnya tidak hilang cuma karena batch ini cuma bawa scan
     * check-out.
     *
     * @return array{0: array, 1: ?string} [entries, max_synced_at (null kalau tidak ada baris baru)]
     */
    private function readAndGroupScansIncremental(?string $sinceDatetime): array
    {
        $query = DB::table('access_logs');
        if ($sinceDatetime) {
            $query->where('access_datetime', '>', $sinceDatetime);
        }

        $rows = $query->orderBy('access_datetime')->get();

        if ($rows->isEmpty()) {
            return [[], null];
        }

        $maxSyncedAt = $rows->max('access_datetime');
        $scanGroups = $rows->groupBy(fn ($row) => $row->employee_id . '|' . $row->access_date);

        $pairs = collect($scanGroups->keys())->map(fn ($key) => explode('|', $key, 2));
        $existing = AttendanceRecords::query()
            ->whereIn('nip', $pairs->pluck(0)->unique()->values())
            ->whereIn('date', $pairs->pluck(1)->unique()->values())
            ->get()
            ->keyBy(fn ($r) => $r->nip . '|' . $r->date);

        $entries = [];
        foreach ($scanGroups as $key => $rowsForDay) {
            [$employeeId, $date] = explode('|', $key, 2);

            $newFirst = $rowsForDay->first()->access_time;
            $newLast = $rowsForDay->last()->access_time;

            $existingRecord = $existing->get($employeeId . '|' . $date);

            if ($existingRecord) {
                // Sudah ada scan sebelumnya buat (nip,date) ini -> check_in dianggap sudah benar
                // (min buat jaga-jaga clock skew), dan sekarang pasti ada >=2 scan total, jadi
                // check_out selalu terisi.
                $checkIn = $existingRecord->check_in_time ? min($existingRecord->check_in_time, $newFirst) : $newFirst;
                $priorCheckOut = $existingRecord->check_out_time ?: $existingRecord->check_in_time;
                $checkOut = $priorCheckOut ? max($priorCheckOut, $newLast) : $newLast;
            } else {
                // Belum pernah ada record -> scan baru INI satu-satunya sumber info hari itu.
                $checkIn = $newFirst;
                $checkOut = $rowsForDay->count() > 1 ? $newLast : null;
            }

            $entries[] = [
                'employee_id' => $employeeId,
                'date' => $date,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ];
        }

        return [$entries, $maxSyncedAt];
    }

    /**
     * Tahap 2: cari User untuk semua employee_id sekaligus (1 query, bukan per-baris).
     */
    private function resolveUsers(array $rawEntries): array
    {
        $employeeIds = collect($rawEntries)->pluck('employee_id')->unique()->values();
        $users       = User::whereIn('nip', $employeeIds)->get()->keyBy('nip');

        $withUser       = [];
        $unmatchedUsers = [];

        foreach ($rawEntries as $entry) {
            $user = $users->get($entry['employee_id']);

            if (! $user) {
                $unmatchedUsers[$entry['employee_id']] = ($unmatchedUsers[$entry['employee_id']] ?? 0) + 1;
                continue;
            }

            $entry['user'] = $user;
            $withUser[]    = $entry;
        }

        return [$withUser, $unmatchedUsers];
    }
}
