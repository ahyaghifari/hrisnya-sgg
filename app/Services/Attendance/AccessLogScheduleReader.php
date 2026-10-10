<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pintu masuk pipeline access_logs -> jadwal. Tugasnya HANYA: ambil, rapikan, cocokkan NIP,
 * cek device, ketahui tipe jadwal, lalu kelompokkan ke calculator-nya.
 *
 *   1. ambil baris access_logs                      (watermark untuk cron, rentang tanggal untuk hitung ulang)
 *   2. buang tap ganda                              (AccessLogScanReader)
 *   3. cocokkan NIP -> user
 *   4. cek device per user & per scan               (AccessLogScanReader)
 *   5. tipe jadwal user per tanggal                 (ScheduleResolver)
 *        - Fixed/Flexible: scan pertama/terakhir hari itu langsung ke calculator-nya
 *          (tidak ada shift lewat tengah malam; masuk-tanpa-pulang / pulang-tanpa-masuk
 *          diputuskan calculator, bukan di sini)
 *        - Shift (atau roster kosong): SELURUHNYA ShiftAttendanceReader - termasuk extra shift,
 *          shift menyambung/terpisah, dan scan konteks kemarin/besok.
 *
 * Reader ini TIDAK memvalidasi shift. Cron (readAndGroupIncremental) hanya memakai scan baru
 * (> watermark) buat menentukan (nip, tanggal) mana yang perlu dihitung; hari itu dibaca penuh.
 *
 * ponytail: effective_from/effective_until pada ScheduleAssignment sengaja DIABAIKAN di sini
 * (lihat parameter $ignoreEffectiveDate di ScheduleResolver) - keputusan eksplisit user untuk
 * tahap eksperimen ini, supaya jadwal contoh yang baru dibuat tetap ke-resolve ke tanggal lama
 * di access_logs. Kode real-time (UI) TIDAK bypass ini, lihat ResolvesTodayAttendanceStatus.
 */
class AccessLogScheduleReader
{
    public function __construct(
        private ScheduleResolver $scheduleResolver,
        private ShiftAttendanceReader $shiftReader,
        private AccessLogScanReader $scans,
    ) {}

    /**
     * Baca ulang [fromDate, toDate] (opsional 1 NIP) - aman diulang berapa kali pun. $fromTime
     * (format "H:i:s") - dipakai sync manual RekapPresensiPage buat abaikan scan sebelum jam
     * tertentu (mis. scan test/salah sebelum device benar2 online), BUKAN dipakai cron.
     *
     * @return array{
     *     buckets: array{fixed: array, flexible: array, shift: array, off: array, extra_shift: array, overtime: array},
     *     unmatched_users: array<string,int>,
     *     unresolved_schedule: array,
     *     rejected_device: array,
     * }
     */
    public function readAndGroup(string $fromDate, string $toDate, ?string $nip = null, ?string $fromTime = null): array
    {
        $query = DB::table('access_logs')->whereBetween('access_date', [$fromDate, $toDate]);
        if ($nip) {
            $query->where('employee_id', $nip);
        }
        if ($fromTime) {
            $query->where('access_time', '>=', $fromTime);
        }

        return $this->process($query->orderBy('access_datetime')->get());
    }

    /**
     * Versi cron: scan baru (access_datetime > $sinceDatetime) hanya dipakai buat MENENTUKAN
     * (nip, tanggal) yang perlu dihitung ulang - tiap scan baru selalu diperiksa, tidak ada yang
     * ditolak kecuali tap ganda. Hari itu dibaca PENUH dari access_logs (scan lama hari yang sama
     * ikut), lalu lewat alur yang sama persis dengan readAndGroup().
     *
     * Entri fixed/flexible/shift membawa is_new_checkin/is_new_checkout (dibanding baris
     * tersimpan) buat notifikasi sekali per event. Baris yang sudah dikoreksi manual
     * (is_auto_calculated=false) dilengkapi, bukan ditimpa.
     *
     * @return array{
     *     buckets: array{fixed: array, flexible: array, shift: array, off: array, extra_shift: array, overtime: array},
     *     unmatched_users: array<string,int>,
     *     unresolved_schedule: array,
     *     rejected_device: array,
     *     max_synced_at: ?string,
     * }
     */
    public function readAndGroupIncremental(?string $sinceDatetime): array
    {
        $query = DB::table('access_logs');
        if ($sinceDatetime) {
            $query->where('access_datetime', '>', $sinceDatetime);
        }

        $newRows = $query->orderBy('access_datetime')->get(['employee_id', 'access_date', 'access_datetime']);

        if ($newRows->isEmpty()) {
            return $this->emptyResult() + ['max_synced_at' => null];
        }

        // Watermark dihitung dari baris MENTAH (sebelum tap ganda dibuang) - kalau dihitung dari
        // hasil dedup, baris yang dibuang akan kebaca ulang terus tiap run berikutnya.
        $maxSyncedAt = $newRows->max('access_datetime');

        $touchedKeys = [];
        foreach ($newRows as $row) {
            $touchedKeys[$row->employee_id . '|' . $row->access_date] = true;
        }

        // Semua scan hari (nip, tanggal) yang tersentuh - bukan cuma yang baru.
        $rows = DB::table('access_logs')
            ->whereIn('employee_id', $newRows->pluck('employee_id')->unique()->values())
            ->whereIn('access_date', $newRows->pluck('access_date')->unique()->values())
            ->orderBy('access_datetime')
            ->get()
            ->filter(fn ($row) => isset($touchedKeys[$row->employee_id . '|' . $row->access_date]))
            ->values();

        $result = $this->process($rows);

        // Jadwal tak ketemu cuma dikabari untuk hari yang BENAR2 kena scan baru, bukan tiap kali
        // hari kemarin ikut dihitung ulang oleh ShiftAttendanceReader.
        $result['unresolved_schedule'] = array_values(array_filter(
            $result['unresolved_schedule'],
            fn (array $e) => isset($touchedKeys[$e['employee_id'] . '|' . $e['date']]),
        ));

        $result['buckets'] = $this->completeWithStoredRecords($result['buckets']);

        return $result + ['max_synced_at' => $maxSyncedAt];
    }

    /** Alur inti: dari baris access_logs mentah (urut access_datetime) sampai bucket. */
    private function process(Collection $rawRows): array
    {
        $rows = $this->scans->dropRepeatedTaps($rawRows);

        [$withUser, $unmatchedUsers] = $this->resolveUsersForRows($rows);
        [$withUser, $rejectedDevice] = $this->scans->splitRejectedDevices($withUser);

        $entries = [];

        foreach ($withUser->groupBy('employee_id') as $userRows) {
            $user = $userRows->first()->user;
            $shiftDates = [];

            foreach ($userRows->groupBy('access_date') as $date => $dayScans) {
                $resolved = $this->scheduleResolver->resolveForUserDate($user, (string) $date, ignoreEffectiveDate: true);

                if ($resolved && in_array($resolved['type'], ['fixed', 'flexible'], true)) {
                    $entries[] = $this->pairPlain($user, (string) $date, $dayScans, $resolved);

                    continue;
                }

                // Shift, atau jadwal kosong/gagal ('libur' - tetap boleh extra shift).
                $shiftDates[] = (string) $date;
            }

            if ($shiftDates !== []) {
                $entries = array_merge($entries, $this->shiftReader->read($user, $shiftDates));
            }
        }

        return $this->bucket($entries, $unmatchedUsers, $rejectedDevice);
    }

    /**
     * Fixed/Flexible: scan pertama = masuk, scan terakhir = pulang (kalau >1 scan). Scan tunggal
     * dibiarkan jadi jam masuk saja - calculator yang memutuskan apakah itu sebenarnya pulang
     * (FixedScheduleCalculator, scan tunggal setelah jam pulang). Hari libur jadwal: bukti lembur
     * kalau ada pengajuan (diputuskan di bucket()), selain itu 'off' (tidak disimpan).
     */
    private function pairPlain(User $user, string $date, Collection $dayScans, array $resolved): array
    {
        $off = (bool) ($resolved['is_off_day'] ?? false);

        return array_merge([
            'employee_id'  => $user->nip,
            'date'         => $date,
            'check_in'     => $dayScans->first()->access_time,
            'check_out'    => $dayScans->count() > 1 ? $dayScans->last()->access_time : null,
            'scan_count'   => $dayScans->count(),
            'device_names' => $dayScans->pluck('device_name')->filter()->unique()->values()->all(),
            'user'         => $user,
        ], $resolved, [
            'bucket'             => $off ? 'off' : $resolved['type'],
            'overtime_candidate' => $off,
        ]);
    }

    private function emptyResult(): array
    {
        return [
            'buckets' => ['fixed' => [], 'flexible' => [], 'shift' => [], 'off' => [], 'extra_shift' => [], 'overtime' => []],
            'unmatched_users' => [],
            'unresolved_schedule' => [],
            'rejected_device' => [],
        ];
    }

    /**
     * Tahap terakhir: entri sudah membawa 'bucket' (diputuskan saat pairing). Satu-satunya yang
     * ditambahkan di sini: hari libur jadwal Fixed/Flexible yang punya pengajuan lembur aktif
     * (menunggu/disetujui) jadi bukti lembur, bukan 'off'.
     */
    private function bucket(array $entries, array $unmatchedUsers, array $rejectedDevice): array
    {
        $result = $this->emptyResult();
        $overtimeKeys = $this->overtimeUserDates(array_filter($entries, fn ($e) => ! empty($e['overtime_candidate'])));

        foreach ($entries as $entry) {
            $bucket = $entry['bucket'];

            if ($bucket === 'unresolved_schedule') {
                $result['unresolved_schedule'][] = $entry;
                continue;
            }

            if (! empty($entry['overtime_candidate']) && isset($overtimeKeys[$entry['user']->id . '|' . $entry['date']])) {
                $bucket = 'overtime';
            }

            $result['buckets'][$bucket][] = $entry;
        }

        $result['unmatched_users'] = $unmatchedUsers;
        $result['rejected_device'] = $rejectedDevice;

        return $result;
    }

    /**
     * Set "user_id|Y-m-d" yang punya pengajuan lembur aktif (menunggu/disetujui) di tanggal itu.
     *
     * @return array<string, true>
     */
    private function overtimeUserDates(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $userIds = collect($entries)->map(fn ($e) => $e['user']->id)->unique()->all();
        $dates = collect($entries)->pluck('date')->unique()->all();

        return AttendanceRequestOvertimeDetails::query()
            ->whereIn('date', $dates)
            ->whereHas('attendanceRequest', fn ($q) => $q->whereIn('user_id', $userIds)->whereIn('status', ['in_review', 'approved']))
            ->with('attendanceRequest:id,user_id')
            ->get()
            ->mapWithKeys(fn ($d) => [$d->attendanceRequest->user_id . '|' . substr((string) $d->date, 0, 10) => true])
            ->all();
    }

    /**
     * Pelengkap khusus cron: bawa is_new_checkin/is_new_checkout (dibanding baris attendance_records
     * yang sudah tersimpan) buat notifikasi sekali per event, dan - kalau baris itu hasil koreksi
     * manual/impor (is_auto_calculated=false) - pertahankan jam masuk yang lebih awal & jam pulang
     * yang lebih akhir.
     *
     * @param  array<string, array<int, array>>  $buckets
     * @return array<string, array<int, array>>
     */
    private function completeWithStoredRecords(array $buckets): array
    {
        $types = ['fixed', 'flexible', 'shift'];
        $entries = collect($types)->flatMap(fn ($type) => $buckets[$type]);

        if ($entries->isEmpty()) {
            return $buckets;
        }

        $stored = AttendanceRecords::query()
            ->whereIn('nip', $entries->pluck('employee_id')->unique()->values())
            ->whereIn('date', $entries->pluck('date')->unique()->values())
            ->get()
            ->keyBy(fn ($r) => $r->nip . '|' . $r->date);

        foreach ($types as $type) {
            $buckets[$type] = array_map(function (array $entry) use ($stored) {
                $record = $stored->get($entry['employee_id'] . '|' . $entry['date']);

                if ($record && ! $record->is_auto_calculated) {
                    if ($entry['config']->is_overnight ?? false) {
                        // Shift lewat tengah malam: jam pulang < jam masuk, min/max tidak berlaku.
                        $entry['check_in'] = $record->check_in_time && $entry['check_in']
                            ? min($record->check_in_time, $entry['check_in'])
                            : ($record->check_in_time ?: $entry['check_in']);
                        $entry['check_out'] = $entry['check_out'] ?: $record->check_out_time;
                    } else {
                        // Semua jam yang diketahui (tersimpan + scan): paling awal = masuk, paling
                        // akhir = pulang. Scan pulang tunggal tidak menghapus jam masuk manual.
                        $times = array_values(array_unique(array_filter([
                            $record->check_in_time, $record->check_out_time, $entry['check_in'], $entry['check_out'],
                        ])));
                        sort($times);
                        $entry['check_in'] = $times[0] ?? null;
                        $entry['check_out'] = count($times) > 1 ? end($times) : null;
                    }
                }

                return $entry + [
                    'is_new_checkin' => filled($entry['check_in']) && blank($record?->check_in_time),
                    'is_new_checkout' => filled($entry['check_out']) && blank($record?->check_out_time),
                ];
            }, $buckets[$type]);
        }

        return $buckets;
    }

    /**
     * Cari User untuk semua employee_id sekaligus (1 query, bukan per-baris), attach ke tiap baris
     * raw - dibutuhkan lebih awal karena device dan jadwal diperiksa per user.
     *
     * @return array{0: Collection, 1: array<string,int>} [rows yang usernya ketemu (ada ->user), unmatched_users]
     */
    private function resolveUsersForRows(Collection $rows): array
    {
        $employeeIds = $rows->pluck('employee_id')->unique()->values();
        $users       = User::whereNip($employeeIds->all())->get()->keyBy('nip');

        $withUser       = collect();
        $unmatchedUsers = [];

        foreach ($rows as $row) {
            $user = $users->get($row->employee_id);

            if (! $user) {
                $unmatchedUsers[$row->employee_id] = ($unmatchedUsers[$row->employee_id] ?? 0) + 1;
                continue;
            }

            $row->user = $user;
            $withUser->push($row);
        }

        return [$withUser, $unmatchedUsers];
    }
}
