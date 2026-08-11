<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Attendance\AttendanceDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 1-4 pipeline access_logs -> jadwal: baca access_logs, cari user (batch), pasangkan
 * check-in/check-out per (user, tanggal shift) lewat pairScansForEmployee(), cari jadwal per
 * (user, tanggal) lewat ScheduleResolver, lalu kelompokkan per tipe jadwal (fixed/flexible/shift)
 * supaya masing2 diserahkan ke calculator-nya sendiri (lihat App\Services\Attendance\Calculators\*).
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
     *     rejected_device: array,
     * }
     */
    public function readAndGroup(string $fromDate, string $toDate, ?string $nip = null, ?string $fromTime = null): array
    {
        [$entries, $unmatchedUsers] = $this->readAndGroupScans($fromDate, $toDate, $nip, $fromTime);

        return $this->groupAndBucket($entries, $unmatchedUsers);
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
     *     rejected_device: array,
     *     max_synced_at: ?string,
     * }
     */
    public function readAndGroupIncremental(?string $sinceDatetime): array
    {
        [$rawEntries, $unmatchedUsers, $maxSyncedAt] = $this->readAndGroupScansIncremental($sinceDatetime);

        return $this->groupAndBucket($rawEntries, $unmatchedUsers) + ['max_synced_at' => $maxSyncedAt];
    }

    /**
     * Tahap 3-4: cek device + resolusi jadwal per entri (yang sudah dipasangkan check-in/
     * check-out-nya, sudah bawa 'user'), lalu kelompokkan ke bucket per tipe jadwal.
     */
    private function groupAndBucket(array $entriesWithUser, array $unmatchedUsers): array
    {
        $buckets = ['fixed' => [], 'flexible' => [], 'shift' => [], 'off' => []];
        $unresolvedSchedule = [];
        $rejectedDevice = [];
        $devicesByCompany = [];

        foreach ($entriesWithUser as $entry) {
            // Device tidak dikenal/beda company -> blokir total, jangan sampai ikut
            // dihitung/disimpan ke attendance_records (pola sama dengan unresolved_schedule).
            if ($this->hasUnrecognizedDevice($entry, $devicesByCompany)) {
                $rejectedDevice[] = $entry;
                continue;
            }

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
            'rejected_device' => $rejectedDevice,
        ];
    }

    /**
     * Tahap 1-2: ambil access_logs, cari User per employee_id, lalu pasangkan check-in/check-out
     * per (user, tanggal shift) lewat pairScansForEmployee() - lihat docblock method itu untuk
     * kenapa tidak bisa sekadar "scan pertama/terakhir per access_date". $fromTime (format
     * "H:i:s") - dipakai sync manual RekapPresensiPage buat abaikan scan sebelum jam tertentu
     * (mis. scan test/salah sebelum device benar2 online), BUKAN dipakai pipeline otomatis
     * (SyncAccessLogsAttendance selalu null di sini).
     *
     * @return array{0: array, 1: array<string,int>} [entries (bawa 'user'), unmatched_users]
     */
    private function readAndGroupScans(string $fromDate, string $toDate, ?string $nip, ?string $fromTime = null): array
    {
        $query = DB::table('access_logs')->whereBetween('access_date', [$fromDate, $toDate]);
        if ($nip) {
            $query->where('employee_id', $nip);
        }
        if ($fromTime) {
            $query->where('access_time', '>=', $fromTime);
        }

        $rows = $query->orderBy('access_datetime')->get();

        [$withUser, $unmatchedUsers] = $this->resolveUsersForRows($rows);

        $entries = [];
        foreach ($withUser->groupBy('employee_id') as $rowsForEmployee) {
            $entries = array_merge($entries, $this->pairScansForEmployee($rowsForEmployee->first()->user, $rowsForEmployee));
        }

        return [$entries, $unmatchedUsers];
    }

    /**
     * Pasangkan scan check-in/check-out 1 karyawan per tanggal SHIFT (bukan per tanggal kalender
     * scan-nya) - shift yang melewati tengah malam (mis. 23:00-08:00) bikin scan check-out jatuh
     * di access_date BESOK, bukan hari yang sama dengan check-in-nya. Kalau cuma dikelompokkan
     * per access_date mentah (cara lama), scan check-out itu jadi entri terpisah dan salah dibaca
     * sebagai check-in untuk tanggal besok. Di sini: kalau tanggal itu cuma py 1 scan DAN jadwal
     * shift-nya is_overnight=true, scan pertama tanggal besoknya "dipinjam" jadi check-out hari
     * ini (dan tidak dipakai lagi buat entri tanggal besok itu sendiri).
     *
     * @return array<int, array{employee_id: string, date: string, check_in: string, check_out: ?string, scan_count: int, device_names: array, user: User}>
     */
    private function pairScansForEmployee(User $user, Collection $rows): array
    {
        $byDate = $rows->groupBy('access_date');
        $dates  = $byDate->keys()->sort()->values();

        $consumedFirstScan = [];
        $entries = [];

        foreach ($dates as $date) {
            $dayScans = $byDate->get($date);
            if (! empty($consumedFirstScan[$date])) {
                $dayScans = $dayScans->slice(1)->values();
            }

            if ($dayScans->isEmpty()) {
                continue;
            }

            $checkIn      = $dayScans->first()->access_time;
            $deviceNames  = $dayScans->pluck('device_name')->filter()->unique()->values();
            $checkOut     = null;
            $scanCount    = $dayScans->count();

            if ($dayScans->count() > 1) {
                $checkOut = $dayScans->last()->access_time;
            } elseif ($this->isOvernightShiftDay($user, $date)) {
                $nextDate  = Carbon::parse($date)->addDay()->toDateString();
                $nextScans = $byDate->get($nextDate);

                if ($nextScans && $nextScans->isNotEmpty() && empty($consumedFirstScan[$nextDate])) {
                    $checkOut = $nextScans->first()->access_time;
                    $consumedFirstScan[$nextDate] = true;
                    $deviceNames = $deviceNames->push($nextScans->first()->device_name)->filter()->unique()->values();
                    $scanCount++;
                }
            } elseif ($this->singleScanIsCheckout($user, $date, $checkIn)) {
                // Scan tunggal ini waktunya sudah masuk jam pulang jadwal Fixed - karyawan
                // kemungkinan lupa/lewat scan masuk paginya, bukan berarti dia scan masuk pas
                // jam pulang. Diperlakukan sebagai check-out saja (check_in tetap kosong), tetap
                // dihitung hadir - lihat FixedScheduleCalculator untuk kelanjutannya.
                $checkOut = $checkIn;
                $checkIn  = null;
            }

            $entries[] = [
                'employee_id'  => $user->nip,
                'date'         => $date,
                'check_in'     => $checkIn,
                'check_out'    => $checkOut,
                'scan_count'   => $scanCount,
                'device_names' => $deviceNames->all(),
                'user'         => $user,
            ];
        }

        return $entries;
    }

    private function isOvernightShiftDay(User $user, string $date): bool
    {
        $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

        return $resolved
            && $resolved['type'] === 'shift'
            && ! ($resolved['is_off_day'] ?? false)
            && (bool) ($resolved['config']->is_overnight ?? false);
    }

    /** Scan tunggal di hari jadwal Fixed, waktunya >= jam pulang (end_time) jadwal itu ->
     * kemungkinan besar itu scan pulang (karyawan lupa/lewat scan masuk), bukan scan masuk yang
     * kebetulan telat sampai jam pulang (instruksi user 2026-08-10). */
    private function singleScanIsCheckout(User $user, string $date, string $scanTime): bool
    {
        $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

        if (! $resolved || $resolved['type'] !== 'fixed' || ($resolved['is_off_day'] ?? false)) {
            return false;
        }

        return $scanTime >= $resolved['config']->end_time;
    }

    /**
     * Versi incremental Tahap 1-2 - cuma ambil baris access_logs baru (access_datetime > since),
     * lalu merge check_in/check_out-nya dengan attendance_records yang sudah ada (kalau ada)
     * biar check_in dari batch sebelumnya tidak hilang cuma karena batch ini cuma bawa scan
     * check-out. Shift is_overnight yang checkout-nya jatuh di access_date besok ditangani lewat
     * lookback ke attendance_records tanggal SEBELUMNYA (lihat blok "lookback checkout shift
     * semalam" di bawah) - pola sama dengan pairScansForEmployee() versi batch, tapi di sini
     * "hari sebelumnya" bisa saja sudah tersimpan dari sync run yang lalu (bukan dalam batch
     * baris baru yang sama), jadi harus dicek ke DB, bukan cuma ke sesama baris baru.
     *
     * ponytail: kalau cron sempat mati beberapa jam PAS lagi ada shift overnight (check-in DAN
     * check-out-nya sama2 baru dalam 1 batch, keduanya belum pernah tersimpan), lookback ini
     * tidak nyambung (belum ada attendance_records buat hari check-in-nya). Kasusnya sangat
     * jarang (butuh downtime cron berjam-jam persis pas ada shift semalam) - tambahkan pairing
     * batch-internal kalau ternyata kejadian.
     *
     * @return array{0: array, 1: array<string,int>, 2: ?string} [entries (bawa 'user'), unmatched_users, max_synced_at]
     */
    private function readAndGroupScansIncremental(?string $sinceDatetime): array
    {
        $query = DB::table('access_logs');
        if ($sinceDatetime) {
            $query->where('access_datetime', '>', $sinceDatetime);
        }

        $rows = $query->orderBy('access_datetime')->get();

        if ($rows->isEmpty()) {
            return [[], [], null];
        }

        $maxSyncedAt = $rows->max('access_datetime');

        [$withUser, $unmatchedUsers] = $this->resolveUsersForRows($rows);

        $scanGroups = $withUser->groupBy(fn ($row) => $row->employee_id . '|' . $row->access_date);

        $dates = $scanGroups->keys()->map(fn ($key) => explode('|', $key, 2)[1]);
        $lookbackDates = $dates->map(fn ($date) => Carbon::parse($date)->subDay()->toDateString());
        $employeeIds = $scanGroups->keys()->map(fn ($key) => explode('|', $key, 2)[0]);

        $existing = AttendanceRecords::query()
            ->whereIn('nip', $employeeIds->unique()->values())
            ->whereIn('date', $dates->merge($lookbackDates)->unique()->values())
            ->get()
            ->keyBy(fn ($r) => $r->nip . '|' . $r->date);

        $entries = [];
        foreach ($scanGroups as $key => $rowsForDay) {
            [$employeeId, $date] = explode('|', $key, 2);
            $user = $rowsForDay->first()->user;

            $newFirst = $rowsForDay->first()->access_time;
            $newLast  = $rowsForDay->last()->access_time;

            $existingRecord = $existing->get($employeeId . '|' . $date);

            // Lookback checkout shift semalam: scan tunggal ini bisa jadi bukan check-in hari
            // ini, tapi check-out shift overnight yang check-in-nya kemarin (masih terbuka -
            // check_out_time kosong).
            if (! $existingRecord && $rowsForDay->count() === 1) {
                $prevDate = Carbon::parse($date)->subDay()->toDateString();
                $prevRecord = $existing->get($employeeId . '|' . $prevDate);

                if ($prevRecord && ! $prevRecord->check_out_time && $this->isOvernightShiftDay($user, $prevDate)) {
                    $entries[] = [
                        'employee_id' => $employeeId,
                        'date' => $prevDate,
                        'check_in' => $prevRecord->check_in_time,
                        'check_out' => $newFirst,
                        'is_new_checkin' => false,
                        'is_new_checkout' => true,
                        'device_names' => $rowsForDay->pluck('device_name')->filter()->unique()->values()->all(),
                        'user' => $user,
                    ];

                    continue;
                }
            }

            if ($existingRecord) {
                // Sudah ada scan sebelumnya buat (nip,date) ini -> check_in dianggap sudah benar
                // (min buat jaga-jaga clock skew), dan sekarang pasti ada >=2 scan total, jadi
                // check_out selalu terisi.
                $checkIn = $existingRecord->check_in_time ? min($existingRecord->check_in_time, $newFirst) : $newFirst;
                $hadCheckOutBefore = filled($existingRecord->check_out_time);
                $priorCheckOut = $existingRecord->check_out_time ?: $existingRecord->check_in_time;
                $checkOut = $priorCheckOut ? max($priorCheckOut, $newLast) : $newLast;
                $isNewCheckIn = false;
                $isNewCheckOut = ! $hadCheckOutBefore && filled($checkOut);
            } elseif ($rowsForDay->count() === 1 && $this->singleScanIsCheckout($user, $date, $newFirst)) {
                // Sama pola dengan pairScansForEmployee() versi batch - scan tunggal ini
                // waktunya sudah masuk jam pulang jadwal Fixed, diperlakukan sebagai check-out
                // saja (check_in kosong), bukan check-in yang kebetulan telat sampai jam pulang.
                $checkIn = null;
                $checkOut = $newFirst;
                $isNewCheckIn = false;
                $isNewCheckOut = true;
            } else {
                // Belum pernah ada record -> scan baru INI satu-satunya sumber info hari itu.
                $checkIn = $newFirst;
                $checkOut = $rowsForDay->count() > 1 ? $newLast : null;
                $isNewCheckIn = true;
                $isNewCheckOut = filled($checkOut);
            }

            $entries[] = [
                'employee_id' => $employeeId,
                'date' => $date,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                // Dipakai SyncAccessLogsAttendance buat kirim notifikasi check-in/check-out cuma
                // sekali per event (bukan tiap batch incremental yang cuma numpang lewat) - lihat
                // App\Notifications\AttendanceCheckedIn/AttendanceCheckedOut.
                'is_new_checkin' => $isNewCheckIn,
                'is_new_checkout' => $isNewCheckOut,
                'device_names' => $rowsForDay->pluck('device_name')->filter()->unique()->values()->all(),
                'user' => $user,
            ];
        }

        return [$entries, $unmatchedUsers, $maxSyncedAt];
    }

    /**
     * Cek device_name yang muncul di scan hari itu ($entry['device_names']) terhadap
     * attendance_devices aktif milik company user - device_names kosong (baris lama tanpa
     * device_name) atau semuanya cocok = lolos. $devicesByCompany dipakai sebagai cache
     * per-request supaya company yang sama tidak query attendance_devices berulang.
     */
    private function hasUnrecognizedDevice(array $entry, array &$devicesByCompany): bool
    {
        if (empty($entry['device_names'])) {
            return false;
        }

        $companyId = $entry['user']->company_id;

        if (! array_key_exists($companyId, $devicesByCompany)) {
            $devicesByCompany[$companyId] = AttendanceDevice::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->pluck('name')
                ->all();
        }

        $knownNames = $devicesByCompany[$companyId];

        foreach ($entry['device_names'] as $deviceName) {
            if (! in_array($deviceName, $knownNames, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tahap 2: cari User untuk semua employee_id sekaligus (1 query, bukan per-baris), attach
     * ke tiap baris raw (bukan ke entri yang sudah dikelompokkan - dibutuhkan lebih awal sekarang
     * karena pairScansForEmployee() perlu User buat resolveForUserDate()).
     *
     * @return array{0: Collection, 1: array<string,int>} [rows yang usernya ketemu (ada ->user), unmatched_users]
     */
    private function resolveUsersForRows(Collection $rows): array
    {
        $employeeIds = $rows->pluck('employee_id')->unique()->values();
        $users       = User::whereIn('nip', $employeeIds)->get()->keyBy('nip');

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
