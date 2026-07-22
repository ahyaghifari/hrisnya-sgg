<?php

namespace App\Services\Attendance;

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
        $rawEntries = $this->readAndGroupScans($fromDate, $toDate, $nip);
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
