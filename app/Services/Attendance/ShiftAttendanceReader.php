<?php

namespace App\Services\Attendance;

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
     * Tahap 1-2 untuk 1 hari yang tipe jadwalnya Shift ATAU resolusi kosong/gagal (basis sentinel
     * 'libur' - member jadwal Shift yang roster-nya belum diisi/off, tetap boleh extra shift).
     * Dipanggil AccessLogScheduleReader (batch DAN incremental) begitu Fixed/Flexible sudah
     * dipastikan BUKAN. Hari tanpa extra shift langsung jadi 1 pasangan normal; hari dengan extra
     * shift dihitung dari SEMUA scan hari itu (bukan state record sebelumnya), aturannya:
     * - libur/kosong (tidak ada shift normal): 1 pasangan, milik extra shift.
     * - normal + extra MENYAMBUNG (isAdjacent): cukup 2 scan (pertama & terakhir), scan di antaranya
     *   diabaikan, batas antar shift sintetis dari jam config - lihat pairAdjacent().
     * - normal + extra TIDAK menyambung (mis. pagi -> malam): >=3 scan dipecah per urutan (2 scan
     *   per shift, splitScans()); 1-2 scan cuma milik satu shift, dilihat dari jam scan pertama.
     * $normalConfig null kalau hari itu 'libur' (tidak ada shift normal, cuma mungkin ada extra).
     *
     * @return array<int, array> 1 atau 2 entri
     */
    public function pairDay(User $user, string $date, Collection $dayScans, ?ScheduleShiftType $normalConfig, Collection $byDate, array &$consumedFirstScan): array
    {
        $extraShift = $this->find($user, $date);

        if ($extraShift && $normalConfig) {
            if ($this->isAdjacent($normalConfig, $extraShift)) {
                return $this->pairAdjacent($user, $date, $dayScans, $normalConfig, $extraShift, $byDate, $consumedFirstScan);
            }

            if ($dayScans->count() >= 3) {
                return $this->splitScans($user, $date, $dayScans, $normalConfig, $extraShift, $byDate, $consumedFirstScan);
            }

            // 1-2 scan, shift tidak menyambung - pasangan ini milik SATU shift saja. Shift yang
            // mulainya belakangan memilikinya kalau scan pertama sudah masuk jendela check-in-nya,
            // kalau belum berarti itu shift yang lebih awal (ini yang menjaga shift normal tidak
            // "dicuri" extra shift yang disetujui tapi tidak dikerjakan).
            $extraIsLater = $extraShift->shiftType->start_time > $normalConfig->start_time;
            $laterConfig = $extraIsLater ? $extraShift->shiftType : $normalConfig;
            $laterOwns = $dayScans->first()->access_time >= $this->checkInWindowStart($laterConfig);
            $extraOwns = $extraIsLater === $laterOwns;
            $owner = $extraOwns ? $extraShift->shiftType : $normalConfig;

            $entry = $this->buildEntryFromScans($user, $date, $dayScans, $byDate, $consumedFirstScan, (bool) $owner->is_overnight);

            if ($entry && ! $extraOwns) {
                $entry['extra_shift_handled'] = true;
            }

            return $entry ? [$entry] : [];
        }

        // Tidak ada extra shift (langsung normal), ATAU libur/kosong + extra (pasangan milik extra
        // shift, jangan ditandai extra_shift_handled). "Pinjam scan besok" (overnight) boleh kalau
        // shift yang berlaku hari itu overnight.
        $allowNextDayBorrow = ($normalConfig?->is_overnight ?? false) || ($extraShift?->shiftType?->is_overnight ?? false);
        $entry = $this->buildEntryFromScans($user, $date, $dayScans, $byDate, $consumedFirstScan, $allowNextDayBorrow);

        return $entry ? [$entry] : [];
    }

    /**
     * Normal + extra yang NEMPEL: karyawan cukup 2 scan (masuk shift pertama, pulang shift kedua).
     * Cuma scan pertama & terakhir yang dipakai; batas antar shift = jam selesai shift yang lebih
     * awal (sintetis, tidak ada scan di titik transisi). Tiap shift dianggap dikerjakan kalau scan
     * jelas jatuh di dalamnya: shift awal kalau scan pertama sebelum jendela check-in shift kedua,
     * shift kedua kalau scan terakhir lewat jendela check-out shift awal. Cuma satu yang jelas
     * dikerjakan -> pasangan aslinya (scan pertama-terakhir) milik shift itu, jadi extra yang
     * tidak dikerjakan tidak menelan scan shift normal (dan sebaliknya). Tak satu pun jelas
     * (scan cuma di sekitar titik transisi) -> dianggap shift normal.
     */
    private function pairAdjacent(User $user, string $date, Collection $scans, ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift, Collection $byDate, array &$consumedFirstScan): array
    {
        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;
        $earlier = $extraIsEarlier ? $extraShift->shiftType : $normalConfig;
        $later = $extraIsEarlier ? $normalConfig : $extraShift->shiftType;
        $earlierIsNormal = ! $extraIsEarlier;

        $whole = $this->buildEntryFromScans($user, $date, $scans, $byDate, $consumedFirstScan, allowNextDayBorrow: true);
        $in = $whole['check_in'];
        $out = $whole['check_out'];

        // Scan tunggal yang meminjam scan besok (shift kedua overnight) = pulang shift kedua.
        $borrowed = $scans->count() === 1 && $out !== null;

        $earlierWorked = $in < $this->checkInWindowStart($later);
        $laterWorked = $out !== null && ($borrowed || $out > $this->checkOutWindowEnd($earlier));

        $make = fn (string $checkIn, ?string $checkOut, bool $isNormal) => array_merge($whole, [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ], $isNormal ? ['extra_shift_handled' => true] : []);

        if ($earlierWorked && $laterWorked) {
            return [
                $make($in, $earlier->end_time, $earlierIsNormal),
                $make($later->start_time, $out, ! $earlierIsNormal),
            ];
        }

        $ownerIsNormal = $earlierWorked ? $earlierIsNormal : ($laterWorked ? ! $earlierIsNormal : true);

        return [$make($in, $out, $ownerIsNormal)];
    }

    // ponytail: string compare jam "H:i:s" - salah kalau shift mulai < buffer menit dari tengah malam
    // (wrap ke hari sebelumnya), belum ada shift seperti itu. Tangani kalau muncul.
    private function checkInWindowStart(ScheduleShiftType $config): string
    {
        return Carbon::createFromFormat('H:i:s', $config->start_time)->subMinutes($config->checkin_buffer_minutes)->format('H:i:s');
    }

    private function checkOutWindowEnd(ScheduleShiftType $config): string
    {
        return Carbon::createFromFormat('H:i:s', $config->end_time)->addMinutes($config->checkout_buffer_minutes)->format('H:i:s');
    }

    /** Shift-nya beneran NEMPEL (jam selesai yang satu = jam mulai yang lain, tanpa jeda) - beda
     * dari "skip" (ada shift yang dilompati di antaranya, mis. normal Pagi lalu extra Malam tanpa
     * pernah masuk Siang). Cuma pola NEMPEL yang boleh cukup 2 scan - pola skip wajib scan
     * lengkap di tiap batas shift (4x). */
    public function isAdjacent(ScheduleShiftType $normalConfig, ExtraShiftAssignment $extraShift): bool
    {
        $extraIsEarlier = $extraShift->shiftType->start_time < $normalConfig->start_time;
        $earlierConfig  = $extraIsEarlier ? $extraShift->shiftType : $normalConfig;
        $laterConfig    = $extraIsEarlier ? $normalConfig : $extraShift->shiftType;

        return $earlierConfig->end_time === $laterConfig->start_time;
    }

    public function find(User $user, string $date): ?ExtraShiftAssignment
    {
        return ExtraShiftAssignment::where('user_id', $user->id)
            ->where('date', $date)
            ->where('status', 'approved')
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

    /**
     * Lookback checkout shift semalam (jalur incremental) - scan tunggal HARI INI bisa jadi
     * bukan check-in hari ini, tapi check-out shift normal overnight KEMARIN yang masih terbuka
     * (attendance_records). Kemarin yang punya extra shift TIDAK lewat sini - dihitung ulang
     * lewat pairDay() di caller. Null kalau tidak ada yang masih terbuka (lanjut pairing hari
     * ini seperti biasa di caller).
     */
    public function resolveOvernightLookbackEntry(
        string $employeeId,
        string $prevDate,
        User $user,
        Collection $rowsForDay,
        string $newFirst,
        ?AttendanceRecords $existingRecord,
        ?AttendanceRecords $prevRecord,
        bool $prevDateIsOvernight,
    ): ?array {
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
}
