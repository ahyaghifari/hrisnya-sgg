<?php

namespace App\Services\Attendance;

use App\Enum\ShiftType;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * SEMUA logic pairing/keputusan buat tipe jadwal SHIFT (normal maupun extra shift) dipisah ke
 * sini dari AccessLogScheduleReader (instruksi user 2026-09-13: reader-nya "khusus scan aja",
 * begitu ketahuan tipe jadwal user itu Shift, LANGSUNG diserahkan ke class ini sepenuhnya -
 * bukan cuma bagian extra shift-nya saja). Fixed/Flexible TIDAK PERNAH lewat sini - itu tetap
 * ditangani AccessLogScheduleReader sendiri (pairing polos, tidak ada extra shift/overnight).
 */
class ShiftAttendanceReader
{
    /**
     * Tahap 1-2 batch UNTUK 1 hari yang tipe jadwalnya Shift ATAU resolusi kosong/gagal (basis
     * sentinel 'libur' - member jadwal Shift yang roster-nya belum diisi/off, tetap boleh extra
     * shift) - dipanggil AccessLogScheduleReader::pairScansForEmployee() begitu Fixed/Flexible
     * sudah dipastikan BUKAN. Split kalau normal+extra ketemu bareng: >=3 scan (splitScans(),
     * batas dari URUTAN scan) atau PERSIS 2 scan (splitTwoScans(), batas SINTETIS dari jam
     * konfigurasi - pola "cuma masuk pagi & pulang malam", tidak ada scan pas transisi shift,
     * instruksi user 2026-09-13). $normalConfig null kalau hari itu 'libur' (tidak ada shift
     * normal, cuma mungkin ada extra shift).
     *
     * @return array<int, array> 1 atau 2 entri
     */
    public function pairDay(User $user, string $date, Collection $dayScans, ?ScheduleShiftType $normalConfig, Collection $byDate, array &$consumedFirstScan): array
    {
        $extraShift = $this->find($user, $date);

        if ($extraShift && $normalConfig) {
            if ($dayScans->count() >= 3) {
                return $this->splitScans($user, $date, $dayScans, $normalConfig, $extraShift, $byDate, $consumedFirstScan);
            }

            if ($dayScans->count() === 2) {
                $firstScan = $dayScans->first()->access_time;
                $lastScan  = $dayScans->last()->access_time;

                if ($this->isPlausibleTwoScanSpan($firstScan, $lastScan, $normalConfig, $extraShift)) {
                    return $this->splitTwoScans($user, $date, $firstScan, $lastScan, $normalConfig, $extraShift);
                }
            }
        }

        // extraShift ada tapi cuma 1 scan (kemungkinan cuma salah satu shift yang kescan hari
        // itu) - TIDAK di-set extra_shift_handled, biar AccessLogScheduleReader::groupAndBucket()
        // tetap mempertimbangkan bucket extra_shift buat entry ini, divalidasi lebih lanjut lewat
        // SyncAccessLogsAttendance::isGenuineExtraShiftScan(). "Pinjam scan besok" (overnight)
        // CUMA boleh kalau normal+extra dua-duanya ADA tapi NEMPEL (isAdjacent) - kalau ada jeda
        // (skip 1 shift, mis. Pagi->lompat Siang->Malam), gak boleh asal gabung 1 scan + pinjeman
        // jadi 1 entry (nyebabin normal hilang total, extra ke-atribusi jam yang salah) - hari
        // yang skip WAJIB scan lengkap (4x: masuk/pulang normal, masuk/pulang extra), bukan
        // ditebak (instruksi user 2026-09-13: "kalo yang skip mungkin harusnya 4 kali absen").
        $normalAndExtraBothExist = $normalConfig && $extraShift;
        $allowNextDayBorrow = $normalAndExtraBothExist
            ? ($this->isAdjacent($normalConfig, $extraShift) && ($normalConfig->is_overnight || $extraShift->shiftType->is_overnight))
            : (($normalConfig?->is_overnight ?? false) || ($extraShift?->shiftType?->is_overnight ?? false));
        $entry = $this->buildEntryFromScans($user, $date, $dayScans, $byDate, $consumedFirstScan, $allowNextDayBorrow);

        return $entry ? [$entry] : [];
    }

    /** Shift-nya beneran NEMPEL (jam selesai yang satu = jam mulai yang lain, tanpa jeda) - beda
     * dari "skip" (ada shift yang dilompati di antaranya, mis. normal Pagi lalu extra Malam tanpa
     * pernah masuk Siang). Cuma pola NEMPEL yang boleh cukup 2 scan / minjem scan besok
     * (instruksi user 2026-09-13) - pola skip wajib scan lengkap di tiap batas shift. */
    public function isAdjacent(ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift): bool
    {
        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;
        $earlierConfig  = $extraIsEarlier ? $extraShift->shiftType : $normalConfig;
        $laterConfig    = $extraIsEarlier ? $normalConfig : $extraShift->shiftType;

        return $earlierConfig->end_time === $laterConfig->start_time;
    }

    /** Batas SINTETIS (bukan dari scan) antara 2 shift yang nempel di 1 hari - dipakai pola
     * "cuma 2 scan" (masuk awal, pulang akhir, TANPA scan di titik transisi shift), baik jalur
     * batch (splitTwoScans()) maupun incremental (splitIncrementalTwoScans()). Shift yang
     * start_time-nya lebih awal "selesai" pas end_time-nya sendiri, shift belakangan "mulai"
     * pas start_time-nya sendiri - asumsi nempel tanpa jeda. Sengaja terima kehilangan akurasi
     * telat/pulang-cepat/bolong PERSIS di titik transisi demi cukup 2 scan (instruksi user
     * 2026-09-13: "kayaknya cukup 2 scan aja").
     *
     * @return array{0: bool, 1: string, 2: string} [extraIsEarlier, jam_selesai_yang_awal, jam_mulai_yang_belakangan]
     */
    private function twoScanBoundary(ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift): array
    {
        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;
        $earlierConfig  = $extraIsEarlier ? $extraShift->shiftType : $normalConfig;
        $laterConfig    = $extraIsEarlier ? $normalConfig : $extraShift->shiftType;

        return [$extraIsEarlier, $earlierConfig->end_time, $laterConfig->start_time];
    }

    /** Cuma masuk akal dipecah jadi 2 (normal+extra) dari 2 scan kalau: (1) shift-nya NEMPEL
     * (isAdjacent() - tanpa jeda; kalau ada shift yang dilompati di antaranya/"skip", scan-nya
     * WAJIB lengkap 4x, tidak boleh ditebak - instruksi user 2026-09-13), (2) scan PERTAMA
     * beneran keplot di jam kerja shift yang lebih awal (bukan udah pas/lewat di jam selesainya
     * - berarti scan itu sebenarnya BUKAN awal shift pertama sama sekali, cuma awal shift kedua),
     * (3) scan TERAKHIR keplot di jam kerja shift yang belakangan. Tanpa cek (2)/(3), hari yang
     * scan-nya CUMA buat extra shift doang (karyawan tidak pernah kerja shift normalnya sama
     * sekali) ikut kepaksa dianggap "2-scan double-duty" dan bikin baris normal palsu durasi
     * nol/negatif (ditemukan 2026-09-13 dari regresi test). */
    public function isPlausibleTwoScanSpan(string $firstScanTime, string $lastScanTime, ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift): bool
    {
        if (! $this->isAdjacent($normalConfig, $extraShift)) {
            return false;
        }

        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;
        $earlierConfig  = $extraIsEarlier ? $extraShift->shiftType : $normalConfig;
        $laterConfig    = $extraIsEarlier ? $normalConfig : $extraShift->shiftType;

        return $firstScanTime < $earlierConfig->end_time && $lastScanTime >= $laterConfig->start_time;
    }

    /** Versi batch dari twoScanBoundary() - dipanggil pairDay() saat PERSIS 2 scan hari itu. */
    private function splitTwoScans(User $user, string $date, string $firstScan, string $lastScan, ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift): array
    {
        [$extraIsEarlier, $boundaryEnd, $boundaryStart] = $this->twoScanBoundary($normalConfig, $extraShift);

        $firstEntry = ['employee_id' => $user->nip, 'date' => $date, 'check_in' => $firstScan, 'check_out' => $boundaryEnd, 'scan_count' => 1, 'device_names' => [], 'user' => $user];
        $secondEntry = ['employee_id' => $user->nip, 'date' => $date, 'check_in' => $boundaryStart, 'check_out' => $lastScan, 'scan_count' => 1, 'device_names' => [], 'user' => $user];

        if ($extraIsEarlier) {
            $secondEntry['extra_shift_handled'] = true;
        } else {
            $firstEntry['extra_shift_handled'] = true;
        }

        return [$firstEntry, $secondEntry];
    }

    /** Versi incremental dari splitTwoScans() - dipakai AccessLogScheduleReader buat 2 kasus:
     * (a) kontak pertama, 2 scan baru sekaligus dalam 1 tick ($firstScanTime/$lastScanTime =
     * scan pertama & terakhir tick ini, $firstIsNewCheckin=true), atau (b) scan BARU ini
     * menutup normal yang sudah kebuka dari tick sebelumnya DAN jamnya sudah masuk jendela
     * extra shift ($firstScanTime = check_in normal yang sudah tersimpan, $firstIsNewCheckin=
     * false karena checkin-nya bukan baru). Dua-duanya tanda pola 2-scan (tidak ada scan pas
     * transisi shift) - dipecah pakai jam KONFIGURASI sebagai batas sintetis. */
    public function splitIncrementalTwoScans(string $employeeId, string $date, string $firstScanTime, string $lastScanTime, bool $firstIsNewCheckin, ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift, Collection $rowsForDay, User $user): array
    {
        [$extraIsEarlier, $boundaryEnd, $boundaryStart] = $this->twoScanBoundary($normalConfig, $extraShift);
        $deviceNames = $rowsForDay->pluck('device_name')->filter()->unique()->values()->all();

        $firstEntry = ['employee_id' => $employeeId, 'date' => $date, 'check_in' => $firstScanTime, 'check_out' => $boundaryEnd, 'is_new_checkin' => $firstIsNewCheckin, 'is_new_checkout' => true, 'device_names' => $deviceNames, 'user' => $user];
        $secondEntry = ['employee_id' => $employeeId, 'date' => $date, 'check_in' => $boundaryStart, 'check_out' => $lastScanTime, 'is_new_checkin' => true, 'is_new_checkout' => true, 'device_names' => $deviceNames, 'user' => $user];

        if ($extraIsEarlier) {
            $secondEntry['extra_shift_handled'] = true;
        } else {
            $firstEntry['extra_shift_handled'] = true;
        }

        return [$firstEntry, $secondEntry];
    }

    /** Oncall SENGAJA tidak pernah balik dari sini (lihat ExtraShiftAssignment::syncOncallRoster())
     * - begitu approved, hari itu ditimpa jadi shift Oncall beneran di schedule_shift_assignments,
     * jadi ScheduleResolver sudah nemuin sendiri tanpa perlu lewat "extra shift". Kalau ikut
     * kebalik dari sini, entry-nya bakal dobel-proses (bucket 'shift' DAN 'extra_shift'). */
    public function find(User $user, string $date): ?ExtraShiftAssignment
    {
        return ExtraShiftAssignment::where('user_id', $user->id)
            ->where('date', $date)
            ->where('status', 'approved')
            ->whereDoesntHave('shiftType', fn ($q) => $q->where('shift_type', ShiftType::ONCALL->value))
            ->with('shiftType')
            ->first();
    }

    public function isOvernight(User $user, string $date): bool
    {
        return (bool) $this->find($user, $date)?->shiftType?->is_overnight;
    }

    /**
     * Batch (bukan N+1) - ambil semua ExtraShiftAssignment approved buat kombinasi (user,date)
     * yang muncul di entri batch ini, keyBy "user_id|date" biar lookup di caller O(1).
     *
     * @return Collection<string, ExtraShiftAssignment>
     */
    public function findForEntries(array $entriesWithUser): Collection
    {
        $userIds = [];
        $dates = [];
        foreach ($entriesWithUser as $entry) {
            $userIds[] = $entry['user']->id;
            $dates[] = $entry['date'];
        }

        if (empty($userIds)) {
            return collect();
        }

        return ExtraShiftAssignment::query()
            ->where('status', 'approved')
            ->whereIn('user_id', array_unique($userIds))
            ->whereIn('date', array_unique($dates))
            ->whereDoesntHave('shiftType', fn ($q) => $q->where('shift_type', ShiftType::ONCALL->value))
            ->with('shiftType')
            ->get()
            ->keyBy(fn (ExtraShiftAssignment $a) => $a->user_id . '|' . $a->date);
    }

    /**
     * Sama seperti findForEntries(), versi jalur incremental - keyBy "nip|date" (bukan
     * "user_id|date") karena caller di jalur incremental kerja dari access_logs mentah yang
     * cuma punya employee_id/nip, dan bawa serta attendanceRecord-nya sendiri (kalau sudah ada
     * scan sebelumnya) buat resolveIncrementalSlot().
     *
     * @return Collection<string, ExtraShiftAssignment>
     */
    public function findForIncrementalDates(Collection $allDates, Collection $employeeIds): Collection
    {
        return ExtraShiftAssignment::query()
            ->where('status', 'approved')
            ->whereIn('date', $allDates)
            ->whereHas('user', fn ($q) => $q->whereIn('nip', $employeeIds))
            ->whereDoesntHave('shiftType', fn ($q) => $q->where('shift_type', ShiftType::ONCALL->value))
            ->with(['shiftType', 'attendanceRecord', 'user:id,nip'])
            ->get()
            ->keyBy(fn (ExtraShiftAssignment $a) => $a->user->nip . '|' . $a->date);
    }

    /**
     * Pecah scan 1 hari jadi 2 pasangan (normal + extra shift) berdasar shift MANA yang jamnya
     * lebih awal - bukan asumsi posisi tetap "normal duluan" atau "extra duluan". 2 scan pertama
     * (urutan waktu) jadi milik shift yang start_time-nya lebih awal, sisanya milik shift yang
     * belakangan (boleh minjem scan besok kalau shift yang belakangan itu overnight).
     *
     * ponytail: sengaja pakai URUTAN scan (posisi), BUKAN jendela jam (start/end+buffer) per
     * shift - dicoba pakai jendela jam dulu, tapi shift yang NEMPEL PAS (mis. Pagi 07-14, Siang
     * 14-21) bikin buffer checkin/checkout kedua shift overlap di sekitar jam sambung (mis.
     * 13:30-14:30 kalau buffer 30 menit), jadi 1 scan bisa "diklaim" dua-duanya - ambigu, tidak
     * ada jawaban benar tanpa asumsi tambahan. Urutan scan tidak kena masalah ini (2026-09-13,
     * ditemukan pas uji shift nempel).
     *
     * @return array<int, array> 1 atau 2 entri (kosong difilter - shift itu bisa saja tidak ada
     *   scan sama sekali hari itu)
     */
    public function splitScans(
        User $user,
        string $date,
        Collection $dayScans,
        ScheduleShiftType $normalConfig,
        ExtraShiftAssignment $extraShift,
        Collection $byDate,
        array &$consumedFirstScan,
    ): array {
        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;
        $secondConfig   = $extraIsEarlier ? $normalConfig : $extraShift->shiftType;

        $firstEntry  = $this->buildEntryFromScans($user, $date, $dayScans->take(2), $byDate, $consumedFirstScan, allowNextDayBorrow: false);
        $secondEntry = $this->buildEntryFromScans($user, $date, $dayScans->slice(2)->values(), $byDate, $consumedFirstScan, allowNextDayBorrow: $secondConfig->is_overnight);

        // extra_shift_handled = "ini pasangan NORMAL, jangan dianggap extra shift lagi di
        // AccessLogScheduleReader::groupAndBucket()" - taruh di entri mana pun yang bukan extra,
        // tergantung urutannya.
        if ($extraIsEarlier) {
            $secondEntry['extra_shift_handled'] = true;
        } else {
            $firstEntry['extra_shift_handled'] = true;
        }

        return array_values(array_filter([$firstEntry, $secondEntry]));
    }

    /**
     * Pasangkan check-in/check-out dari 1 kumpulan scan yang SUDAH ditentukan milik 1 shift
     * (normal ATAU extra, hasil split di splitScans()). Null kalau scan-nya kosong (shift itu
     * tidak ada scan sama sekali hari itu - wajar, mis. tidak masuk). Boleh minjem scan pertama
     * besok jadi check-out KALAU $allowNextDayBorrow (shift ini overnight) - $consumedFirstScan
     * dilewatkan by-reference biar loop tanggal besok di pairScansForEmployee() tau scan
     * pertamanya sudah dipakai di sini.
     */
    public function buildEntryFromScans(User $user, string $date, Collection $scans, Collection $byDate, array &$consumedFirstScan, bool $allowNextDayBorrow): ?array
    {
        if ($scans->isEmpty()) {
            return null;
        }

        $checkIn     = $scans->first()->access_time;
        $deviceNames = $scans->pluck('device_name')->filter()->unique()->values();
        $checkOut    = null;
        $scanCount   = $scans->count();

        if ($scans->count() > 1) {
            $checkOut = $scans->last()->access_time;
        } elseif ($allowNextDayBorrow) {
            $nextDate  = Carbon::parse($date)->addDay()->toDateString();
            $nextScans = $byDate->get($nextDate);

            if ($nextScans && $nextScans->isNotEmpty() && empty($consumedFirstScan[$nextDate])) {
                $checkOut = $nextScans->first()->access_time;
                $consumedFirstScan[$nextDate] = true;
                $deviceNames = $deviceNames->push($nextScans->first()->device_name)->filter()->unique()->values();
                $scanCount++;
            }
        }

        return [
            'employee_id'  => $user->nip,
            'date'         => $date,
            'check_in'     => $checkIn,
            'check_out'    => $checkOut,
            'scan_count'   => $scanCount,
            'device_names' => $deviceNames->all(),
            'user'         => $user,
        ];
    }

    /** Versi incremental dari buildEntryFromScans() - tanpa borrow next-day (kontak pertama
     * biasanya belum lengkap 2 sisi, checkout overnight nyambung lewat lookback tick berikutnya
     * di AccessLogScheduleReader, bukan di sini) dan pakai flag is_new_checkin/is_new_checkout
     * (bukan scan_count) sesuai bentuk entri jalur incremental. Null kalau scan-nya kosong. */
    public function buildIncrementalEntryFromScans(string $employeeId, string $date, Collection $scans, User $user): ?array
    {
        if ($scans->isEmpty()) {
            return null;
        }

        return [
            'employee_id' => $employeeId,
            'date' => $date,
            'check_in' => $scans->first()->access_time,
            'check_out' => $scans->count() > 1 ? $scans->last()->access_time : null,
            'is_new_checkin' => true,
            'is_new_checkout' => $scans->count() > 1,
            'device_names' => $scans->pluck('device_name')->filter()->unique()->values()->all(),
            'user' => $user,
        ];
    }

    /**
     * Split versi kontak pertama jalur incremental (>=3 scan baru sekaligus, mis. cron sempat
     * mati/catch-up) - pola sama dengan splitScans() (urutan/posisi, bukan jendela jam), cuma
     * beda bentuk entri (is_new_checkin/is_new_checkout, bukan scan_count).
     *
     * @return array<int, array>
     */
    public function splitIncrementalScans(string $employeeId, string $date, Collection $rowsForDay, User $user, ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift): array
    {
        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;

        $firstEntry  = $this->buildIncrementalEntryFromScans($employeeId, $date, $rowsForDay->take(2), $user);
        $secondEntry = $this->buildIncrementalEntryFromScans($employeeId, $date, $rowsForDay->slice(2)->values(), $user);

        if ($extraIsEarlier) {
            if ($secondEntry) {
                $secondEntry['extra_shift_handled'] = true;
            }
        } elseif ($firstEntry) {
            $firstEntry['extra_shift_handled'] = true;
        }

        return array_values(array_filter([$firstEntry, $secondEntry]));
    }

    /**
     * Lookback checkout shift semalam (jalur incremental) - scan tunggal HARI INI bisa jadi
     * bukan check-in hari ini, tapi check-out shift overnight KEMARIN yang masih terbuka. Extra
     * shift overnight dicek DULUAN (pairing-nya sendiri, attendanceRecord milik
     * ExtraShiftAssignment), baru shift normal overnight biasa (attendance_records). Null kalau
     * tidak ada shift overnight kemarin yang masih terbuka (lanjut pairing hari ini seperti
     * biasa di caller).
     */
    public function resolveOvernightLookbackEntry(
        string $employeeId,
        string $prevDate,
        User $user,
        Collection $rowsForDay,
        string $newFirst,
        ?ExtraShiftAssignment $prevExtraShift,
        ?AttendanceRecords $existingRecord,
        ?AttendanceRecords $prevRecord,
        bool $prevDateIsOvernight,
    ): ?array {
        $prevExtraRecord = $prevExtraShift?->attendanceRecord;

        if ($prevExtraShift?->shiftType?->is_overnight && $prevExtraRecord && ! $prevExtraRecord->check_out_time) {
            return [
                'employee_id' => $employeeId,
                'date' => $prevDate,
                'check_in' => $prevExtraRecord->check_in_time,
                'check_out' => $newFirst,
                'is_new_checkin' => false,
                'is_new_checkout' => true,
                'device_names' => $rowsForDay->pluck('device_name')->filter()->unique()->values()->all(),
                'user' => $user,
            ];
        }

        if (! $existingRecord && $prevRecord && ! $prevRecord->check_out_time && $prevDateIsOvernight) {
            return [
                'employee_id' => $employeeId,
                'date' => $prevDate,
                'check_in' => $prevRecord->check_in_time,
                'check_out' => $newFirst,
                'is_new_checkin' => false,
                'is_new_checkout' => true,
                'device_names' => $rowsForDay->pluck('device_name')->filter()->unique()->values()->all(),
                'user' => $user,
            ];
        }

        return null;
    }

    /**
     * Scan baru (incremental) ini nyambung ke pairing NORMAL atau EXTRA shift hari itu:
     * - Extra shift-nya sendiri masih "kebuka" (sudah ada check_in, belum check_out) -> pasti
     *   lanjutan extra shift, apapun jam scan barunya.
     * - Extra shift-nya SUDAH SELESAI (check_in DAN check_out keduanya terisi) -> scan baru ini
     *   pasti bukan bagian extra lagi, pasti buat shift lain (normal) - sebelumnya tidak dicek,
     *   bikin scan sesudah extra selesai ikut ketarik molor jadi checkout extra terus-menerus
     *   kalau normal belum pernah punya baris (2026-09-13, laporan user: extra shift jamnya
     *   lebih awal dari normal, checkout normal ikut kehitung checkout extra).
     * - Normal shift hari itu sudah SELESAI (check_out_time terisi) DAN scan baru ini jamnya
     *   sudah masuk jendela extra shift -> ini awal extra shift, bukan lanjutan normal.
     * - Selain itu -> 'normal' (default aman, extra shift belum disentuh / datanya belum cukup
     *   buat mastiin).
     */
    public function resolveIncrementalSlot(ExtraShiftAssignment $extraShift, ?AttendanceRecords $existingNormal, string $newScanTime): string
    {
        $extraRecord = $extraShift->attendanceRecord;

        if ($extraRecord?->check_in_time && ! $extraRecord->check_out_time) {
            return 'extra';
        }

        if ($extraRecord?->check_in_time && $extraRecord->check_out_time) {
            return 'normal';
        }

        // Pairing normal TIDAK "kebuka" buat nampung scan baru ini - baik karena belum pernah
        // ada scan sama sekali (karyawan cuma scan buat extra shift-nya) maupun karena shift
        // normalnya sudah SELESAI (check_out_time terisi) - jadi kalau jamnya juga cocok buat
        // extra shift, anggap ini extra shift.
        $normalSlotIsOpen = $existingNormal && ! $existingNormal->check_out_time;

        if (! $normalSlotIsOpen && $newScanTime >= $extraShift->shiftType->start_time) {
            return 'extra';
        }

        return 'normal';
    }

    /** Bangun entri bucket 'extra_shift' hasil lanjutan pairing (resolveIncrementalSlot()==='extra')
     * - merge sama attendanceRecord lama (kalau ada) biar check_in/check_out yang sudah benar
     * dari tick sebelumnya tidak ketiban scan baru yang notabene cuma salah satu sisi. */
    public function mergeIncrementalExtraEntry(string $employeeId, string $date, ExtraShiftAssignment $extraShift, Collection $rowsForDay, string $newFirst, string $newLast, User $user): array
    {
        $extraRecord = $extraShift->attendanceRecord;
        $checkIn = $extraRecord?->check_in_time ? min($extraRecord->check_in_time, $newFirst) : $newFirst;
        $hadCheckOutBefore = filled($extraRecord?->check_out_time);
        $priorCheckOut = $extraRecord?->check_out_time ?: $extraRecord?->check_in_time;
        $checkOut = $priorCheckOut ? max($priorCheckOut, $newLast) : ($rowsForDay->count() > 1 ? $newLast : null);

        return [
            'employee_id' => $employeeId,
            'date' => $date,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'is_new_checkin' => ! $extraRecord,
            'is_new_checkout' => ! $hadCheckOutBefore && filled($checkOut),
            'device_names' => $rowsForDay->pluck('device_name')->filter()->unique()->values()->all(),
            'user' => $user,
        ];
    }
}
