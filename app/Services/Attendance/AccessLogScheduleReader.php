<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Attendance\AttendanceDevice;
use App\Models\Shift\ScheduleShiftType;
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
    public function __construct(
        private ScheduleResolver $scheduleResolver,
        private ShiftAttendanceReader $shiftReader,
    ) {}

    /**
     * @return array{
     *     buckets: array{fixed: array, flexible: array, shift: array, off: array, extra_shift: array},
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
     *     buckets: array{fixed: array, flexible: array, shift: array, off: array, extra_shift: array},
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
     *
     * Hari yang punya extra shift approved DILEWATI dari resolusi jadwal normal sama sekali,
     * langsung masuk bucket 'extra_shift' (config-nya shift_type dari ExtraShiftAssignment) -
     * reader di sini cuma nentuin ADA/TIDAKNYA extra shift, bukan tempatnya validasi jam. Cek
     * "pasangan check-in/check-out ini beneran extra shift atau ternyata cuma shift normal
     * (belum lewat jam mulai extra)" ada di sisi konsumen bucket ini, lihat
     * SyncAccessLogsAttendance::isGenuineExtraShiftScan() - kalau ternyata tidak valid, boleh
     * saja dicek ulang ke ScheduleResolver dari sana, tidak masalah.
     */
    private function groupAndBucket(array $entriesWithUser, array $unmatchedUsers): array
    {
        $buckets = ['fixed' => [], 'flexible' => [], 'shift' => [], 'off' => [], 'extra_shift' => []];
        $unresolvedSchedule = [];
        $rejectedDevice = [];
        $devicesByCompany = [];
        $extraShiftsByUserDate = $this->shiftReader->findForEntries($entriesWithUser);

        foreach ($entriesWithUser as $entry) {
            // Device tidak dikenal/beda company -> blokir total, jangan sampai ikut
            // dihitung/disimpan ke attendance_records (pola sama dengan unresolved_schedule).
            if ($this->hasUnrecognizedDevice($entry, $devicesByCompany)) {
                $rejectedDevice[] = $entry;
                continue;
            }

            // Tipe jadwal DULU, baru extra shift - bukan sebaliknya (instruksi user 2026-09-13).
            // Fixed/Flexible TIDAK PERNAH punya konsep extra shift (lihat
            // ResolvesExtraShiftOptions::resolveNormalShift() - basisnya cuma jadwal tipe Shift,
            // atau 'libur' buat member Shift yang roster-nya kosong), jadi begitu ketahuan
            // tipenya Fixed/Flexible, langsung ke calculator masing2 tanpa pernah mampir cek
            // extra shift sama sekali - biar tidak ada celah baris ExtraShiftAssignment nyasar
            // (mis. diinput manual/salah) ikut membajak routing user Fixed/Flexible.
            $resolved = $this->scheduleResolver->resolveForUserDate($entry['user'], $entry['date'], ignoreEffectiveDate: true);

            if ($resolved && in_array($resolved['type'], ['fixed', 'flexible'], true)) {
                $buckets[$resolved['type']][] = array_merge($entry, $resolved);
                continue;
            }

            // Sampai sini: tipe Shift, ATAU resolusi gagal (unresolved - termasuk kasus roster
            // kosong yang tetap boleh extra shift, basis kategori 'libur') - extra shift baru
            // relevan diperiksa di sini.
            $extraShift = $extraShiftsByUserDate->get($entry['user']->id . '|' . $entry['date']);

            // extra_shift_handled - pairScansForEmployee() sudah misahin pasangan normal dari
            // pasangan extra shift-nya sendiri (kasus overnight/nempel, lihat komentar di sana),
            // entry ini murni pasangan normal, jangan dianggap extra shift lagi di sini.
            if ($extraShift && empty($entry['extra_shift_handled'])) {
                $entry['config'] = $extraShift->shiftType;
                $entry['extra_shift_assignment_id'] = $extraShift->id;
                $buckets['extra_shift'][] = $entry;
                continue;
            }

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

            // Tipe jadwal DULU (instruksi user 2026-09-13, sama pola dengan groupAndBucket()) -
            // Fixed/Flexible dipasangkan polos di sini (tidak ada extra shift/overnight sama
            // sekali buat 2 tipe itu). Shift (ATAU resolusi kosong/gagal - basis 'libur', tetap
            // boleh extra shift) LANGSUNG diserahkan sepenuhnya ke ShiftAttendanceReader -
            // reader di sini tidak pegang aturan bisnis shift apa pun lagi.
            $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

            if ($resolved && in_array($resolved['type'], ['fixed', 'flexible'], true)) {
                $checkIn     = $dayScans->first()->access_time;
                $deviceNames = $dayScans->pluck('device_name')->filter()->unique()->values();
                $checkOut    = null;

                if ($dayScans->count() > 1) {
                    $checkOut = $dayScans->last()->access_time;
                } elseif ($this->singleScanIsCheckout($user, $date, $checkIn)) {
                    // Scan tunggal ini waktunya sudah masuk jam pulang jadwal Fixed - karyawan
                    // kemungkinan lupa/lewat scan masuk paginya, bukan berarti dia scan masuk pas
                    // jam pulang. Diperlakukan sebagai check-out saja (check_in tetap kosong),
                    // tetap dihitung hadir - lihat FixedScheduleCalculator untuk kelanjutannya.
                    $checkOut = $checkIn;
                    $checkIn  = null;
                }

                $entries[] = [
                    'employee_id'  => $user->nip,
                    'date'         => $date,
                    'check_in'     => $checkIn,
                    'check_out'    => $checkOut,
                    'scan_count'   => $dayScans->count(),
                    'device_names' => $deviceNames->all(),
                    'user'         => $user,
                ];

                continue;
            }

            $normalConfig = ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false))
                ? $resolved['config']
                : null;

            $entries = array_merge($entries, $this->shiftReader->pairDay($user, $date, $dayScans, $normalConfig, $byDate, $consumedFirstScan));
        }

        return $entries;
    }

    /** Termasuk overnight kalau jadwal NORMAL hari itu overnight, ATAU extra shift approved
     * hari itu overnight - dua-duanya berhak "minjem" scan pertama besok jadi check-out. Dipakai
     * jalur incremental (lookback checkout shift semalam) - jalur batch sudah lewat
     * ShiftAttendanceReader::pairDay() sepenuhnya, tidak butuh method ini lagi. */
    private function isOvernightShiftDay(User $user, string $date): bool
    {
        $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

        if ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false) && ($resolved['config']->is_overnight ?? false)) {
            return true;
        }

        return $this->shiftReader->isOvernight($user, $date);
    }

    /** Jadwal SHIFT normal (bukan off_day, bukan fixed/flexible) user di tanggal itu - dipakai
     * jalur incremental buat split kontak pertama (ShiftAttendanceReader::splitIncrementalScans()).
     * Null kalau hari itu libur/kosong atau jadwal user bukan tipe shift. */
    private function normalShiftConfigFor(User $user, string $date): ?ScheduleShiftType
    {
        $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

        if ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false)) {
            return $resolved['config'] ?? null;
        }

        return null;
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
        $allDates = $dates->merge($lookbackDates)->unique()->values();

        $existing = AttendanceRecords::query()
            ->whereIn('nip', $employeeIds->unique()->values())
            ->whereIn('date', $allDates)
            ->get()
            ->keyBy(fn ($r) => $r->nip . '|' . $r->date);

        // Extra shift approved buat kombinasi (nip,date) yang relevan batch ini, bawa serta
        // attendanceRecord-nya sendiri (kalau sudah ada scan sebelumnya) - dipakai
        // ShiftAttendanceReader::resolveIncrementalSlot() mutusin scan baru ini nyambung ke
        // pairing NORMAL atau EXTRA, biar scan extra shift tidak nimpa check_out shift normal
        // yang sudah benar.
        $extraShifts = $this->shiftReader->findForIncrementalDates($allDates, $employeeIds->unique()->values());

        $entries = [];
        foreach ($scanGroups as $key => $rowsForDay) {
            [$employeeId, $date] = explode('|', $key, 2);
            $user = $rowsForDay->first()->user;

            $newFirst = $rowsForDay->first()->access_time;
            $newLast  = $rowsForDay->last()->access_time;

            $existingRecord = $existing->get($employeeId . '|' . $date);
            $extraShift = $extraShifts->get($employeeId . '|' . $date);

            // Lookback checkout shift semalam - scan tunggal ini bisa jadi bukan check-in hari
            // ini, tapi check-out shift overnight yang check-in-nya kemarin (masih terbuka).
            // Sepenuhnya shift-specific, diserahkan ke ShiftAttendanceReader.
            if ($rowsForDay->count() === 1) {
                $prevDate = Carbon::parse($date)->subDay()->toDateString();
                $prevExtraShift = $extraShifts->get($employeeId . '|' . $prevDate);
                $prevRecord = $existing->get($employeeId . '|' . $prevDate);

                // Kalau kemarin ada normal DAN extra shift dua-duanya tapi TIDAK nempel (skip 1
                // shift, mis. Pagi lalu lompat ke Malam) - jangan asal pinjem scan hari ini buat
                // nutup baris normal kemarin, itu bisa salah atribusi total (baris normal jadi
                // ketiban jam checkout shift LAIN). Skip-pattern wajib scan lengkap 4x, bukan
                // ditebak (instruksi user 2026-09-13).
                $prevNormalConfig = $this->normalShiftConfigFor($user, $prevDate);
                $prevDateIsOvernight = ($prevNormalConfig && $prevExtraShift)
                    ? ($this->shiftReader->isAdjacent($prevNormalConfig, $prevExtraShift) && ($prevNormalConfig->is_overnight || $prevExtraShift->shiftType->is_overnight))
                    : $this->isOvernightShiftDay($user, $prevDate);

                $lookbackEntry = $this->shiftReader->resolveOvernightLookbackEntry(
                    $employeeId,
                    $prevDate,
                    $user,
                    $rowsForDay,
                    $newFirst,
                    $prevExtraShift,
                    $existingRecord,
                    $prevRecord,
                    $prevDateIsOvernight,
                );

                if ($lookbackEntry) {
                    $entries[] = $lookbackEntry;

                    continue;
                }
            }

            // >=3 scan BARU sekaligus dalam 1 batch (mis. cron sempat mati/catch-up, atau
            // "Sync Sekarang" manual sesudah karyawan sudah selesai kedua shift-nya), DAN belum
            // ada apa pun tercatat buat normal MAUPUN extra hari itu -> berpotensi ini 2 blok
            // shift berbeda sekaligus. Diserahkan ke ShiftAttendanceReader::splitIncrementalScans()
            // (urutan/posisi scan, bukan jendela jam - lihat catatan ponytail di
            // pairScansForEmployee()). Kalau cuma 1-2 scan baru (kasus normal tiap menit), tetap
            // lewat resolveIncrementalSlot() seperti biasa.
            if ($extraShift && $rowsForDay->count() >= 3 && ! $existingRecord && ! $extraShift->attendanceRecord) {
                $normalConfig = $this->normalShiftConfigFor($user, $date);

                if ($normalConfig) {
                    foreach ($this->shiftReader->splitIncrementalScans($employeeId, $date, $rowsForDay, $user, $normalConfig, $extraShift) as $splitEntry) {
                        $entries[] = $splitEntry;
                    }

                    continue;
                }
            }

            // PERSIS 2 scan: baik dua-duanya baru sekaligus (kontak pertama, 1 tick bawa 2 scan
            // baru) ATAU 1 scan baru menutup normal yang sudah kebuka dari tick sebelumnya DAN
            // jamnya sudah masuk jendela extra shift - tanda pola "cuma masuk pagi & pulang
            // malam" (tidak ada scan pas transisi shift). Dipecah pakai jam konfigurasi sebagai
            // batas sintetis, sama seperti ShiftAttendanceReader::splitTwoScans() versi batch
            // (instruksi user 2026-09-13: "kayaknya cukup 2 scan aja").
            if ($extraShift && ! $extraShift->attendanceRecord) {
                $normalConfig = $this->normalShiftConfigFor($user, $date);
                $twoScanTrigger = null;

                if ($normalConfig && ! $existingRecord && $rowsForDay->count() === 2
                    && $this->shiftReader->isPlausibleTwoScanSpan($newFirst, $newLast, $normalConfig, $extraShift)) {
                    $twoScanTrigger = [$newFirst, $newLast, true];
                } elseif ($normalConfig && $existingRecord && ! $existingRecord->check_out_time
                    && $this->shiftReader->isPlausibleTwoScanSpan($existingRecord->check_in_time, $newLast, $normalConfig, $extraShift)) {
                    $twoScanTrigger = [$existingRecord->check_in_time, $newLast, false];
                }

                if ($twoScanTrigger) {
                    [$firstScanTime, $lastScanTime, $firstIsNewCheckin] = $twoScanTrigger;

                    foreach ($this->shiftReader->splitIncrementalTwoScans($employeeId, $date, $firstScanTime, $lastScanTime, $firstIsNewCheckin, $normalConfig, $extraShift, $rowsForDay, $user) as $splitEntry) {
                        $entries[] = $splitEntry;
                    }

                    continue;
                }
            }

            $slot = $extraShift ? $this->shiftReader->resolveIncrementalSlot($extraShift, $existingRecord, $newFirst) : 'normal';

            if ($slot === 'extra') {
                // Nyambung ke pairing extra shift-nya sendiri (extra_shift_attendance_records),
                // BUKAN attendance_records normal - lihat resolveIncrementalSlot().
                $entries[] = $this->shiftReader->mergeIncrementalExtraEntry($employeeId, $date, $extraShift, $rowsForDay, $newFirst, $newLast, $user);

                continue;
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
                // extraShift ada tapi slot-nya 'normal' (extra shift belum disentuh hari ini) -
                // jangan dianggap extra shift lagi oleh groupAndBucket(), ini murni pairing normal.
                ...($extraShift ? ['extra_shift_handled' => true] : []),
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
