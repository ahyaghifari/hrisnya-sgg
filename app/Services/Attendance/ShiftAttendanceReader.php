<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Enum\ShiftType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * SEMUA logic pairing/keputusan buat tipe jadwal SHIFT (normal maupun extra shift) ada di sini
 * (instruksi user 2026-09-13: AccessLogScheduleReader "khusus scan aja", begitu ketahuan tipe
 * jadwal user itu Shift, LANGSUNG diserahkan ke class ini sepenuhnya). Fixed/Flexible TIDAK
 * PERNAH lewat sini.
 *
 * Reader utama hanya menyerahkan (user, tanggal tersentuh). Scan konteks yang dibutuhkan (kemarin,
 * besok, rantai malam berturut-turut) diambil class ini sendiri lewat AccessLogScanReader - reader
 * utama tidak tahu apa pun soal shift lewat tengah malam.
 */
class ShiftAttendanceReader
{
    /** Batas mundur rantai shift lewat tengah malam berturut2. */
    private const MAX_OVERNIGHT_CHAIN_DAYS = 31;

    /** Toleransi scan lembur sebelum jam mulai / sesudah jam selesai pengajuan. */
    private const OVERTIME_SCAN_TOLERANCE_MINUTES = 30;

    public function __construct(
        private ScheduleResolver $scheduleResolver,
        private AccessLogScanReader $scans,
    ) {}

    /**
     * Entri pasangan shift untuk tanggal-tanggal $dates milik $user (tanggal yang jadwalnya
     * Fixed/Flexible dilewati - itu urusan reader utama). Hasil mencakup SEHARI sebelum tanggal
     * pertama (scan pagi bisa menutup shift lewat tengah malam kemarin) dan setiap entri sudah
     * bertanda 'bucket' (shift | extra_shift | off | unresolved_schedule) + hasil resolusi jadwal.
     *
     * Rentang scan yang dibaca: mundur sampai awal rantai shift lewat tengah malam, maju 1 hari
     * (scan pertama besok dipinjam jadi pulang shift overnight hari ini).
     *
     * @param  array<int, string>  $dates
     * @return array<int, array>
     */
    public function read(User $user, array $dates): array
    {
        sort($dates);

        $keepFrom = Carbon::parse($dates[0])->subDay();
        $readFrom = $keepFrom->copy();
        $last = end($dates);

        for ($i = 0; $i < self::MAX_OVERNIGHT_CHAIN_DAYS
            && $this->isOvernightShiftDay($user, $readFrom->copy()->subDay()->toDateString()); $i++) {
            $readFrom->subDay();
        }

        $byDate = $this->scans
            ->forUser($user, $readFrom->toDateString(), Carbon::parse($last)->addDay()->toDateString())
            ->groupBy('access_date');

        $consumedFirstScan = [];
        $entries = [];

        foreach ($byDate->keys()->sort()->values() as $date) {
            $dayScans = $byDate->get($date);

            // Scan pertama hari ini sudah "dipinjam" jadi pulang shift lewat tengah malam kemarin -
            // dibuang dari hari ini, KECUALI juga jam masuk shift hari ini.
            if (! empty($consumedFirstScan[$date])
                && ! ($dayScans->isNotEmpty() && $this->borrowedScanIsAlsoCheckIn($user, $date, $dayScans->first()->access_time))) {
                $dayScans = $dayScans->slice(1)->values();
            }

            // Scan pertama pagi ini = pulang shift lewat tengah malam KEMARIN yang lupa scan masuk
            // (kemarin tidak menghasilkan entri) - bukan jam masuk hari ini.
            if ($dayScans->isNotEmpty() && empty($consumedFirstScan[$date])
                && ($checkoutOnly = $this->overnightCheckoutOnlyEntry($user, $date, $dayScans->first(), array_column($entries, 'date')))) {
                $entries[] = $this->classifyShiftEntry($checkoutOnly);
                $consumedFirstScan[$date] = true;
                $dayScans = $dayScans->slice(1)->values();
            }

            if ($dayScans->isEmpty()) {
                continue;
            }

            $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

            if ($resolved && in_array($resolved['type'], ['fixed', 'flexible'], true)) {
                continue;
            }

            $normalConfig = ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false))
                ? $resolved['config']
                : null;

            // Scan jam lembur (di luar jam shift) bukan bukti shift - jangan jadi masuk/pulang shift.
            $dayScans = $this->withoutOvertimeScans($user, $date, $dayScans);

            if ($dayScans->isEmpty()) {
                continue;
            }

            foreach ($this->pairDay($user, $date, $dayScans, $normalConfig, $byDate, $consumedFirstScan) as $entry) {
                $entries[] = $this->classifyShiftEntry($entry);
            }
        }

        $keepFromDate = $keepFrom->toDateString();

        return array_values(array_filter($entries, fn (array $e) => $e['date'] >= $keepFromDate && $e['date'] <= $last));
    }

    /**
     * Tentukan bucket entri hasil pairing (sudah bertanda owner normal/extra):
     * - extra yang beneran jam extra-nya -> 'extra_shift' (config shift extra sudah dibawa).
     * - normal, atau "extra" yang ternyata scan shift normal -> jadwal normal hari itu: tak ketemu
     *   -> unresolved_schedule; libur -> 'off' (extra yang jatuh ke sini: unresolved, karena tidak
     *   ada jadwal yang bisa memilikinya); selain itu bucket tipenya.
     */
    private function classifyShiftEntry(array $entry): array
    {
        if ($entry['owner'] === 'extra' && $this->isGenuineExtraEntry($entry)) {
            return $entry + ['bucket' => 'extra_shift'];
        }

        $resolved = $this->scheduleResolver->resolveForUserDate($entry['user'], $entry['date'], ignoreEffectiveDate: true);

        if (! $resolved) {
            return $entry + ['bucket' => 'unresolved_schedule'];
        }

        if ($resolved['is_off_day'] ?? false) {
            return $entry + ['bucket' => $entry['owner'] === 'extra' ? 'unresolved_schedule' : 'off'];
        }

        return array_merge($entry, $resolved, ['bucket' => $resolved['type']]);
    }

    /**
     * Scan pertama hari ini yang sudah "dipinjam" jadi pulang shift lewat tengah malam kemarin
     * TETAP jam masuk hari ini kalau jatuh di jendela masuk shift hari ini (normal/extra) - mis.
     * malam kemarin selesai 08:00, pagi ini masuk Pagi 08:00 + extra Siang: 1 scan 08:00 dipakai dua
     * kali (cukup 2 scan sehari, 08:00 & 20:00). Berlaku juga saat scan itu satu-satunya hari ini
     * (belum pulang) - jadwal Pagi tepat setelah malam berarti memang lanjut kerja.
     */
    private function borrowedScanIsAlsoCheckIn(User $user, string $date, string $scanTime): bool
    {
        $configs = [$this->normalShiftConfigFor($user, $date), $this->find($user, $date)?->shiftType];

        foreach ($configs as $config) {
            if ($config && ! $config->is_overnight
                && ! $this->isBeforeCheckInWindow($scanTime, $config)
                && $scanTime < $config->end_time) {
                return true;
            }
        }

        return false;
    }

    /**
     * Buang scan yang jatuh di jendela pengajuan lembur aktif (menunggu/disetujui, toleransi
     * OVERTIME_SCAN_TOLERANCE_MINUTES) DAN di luar jendela shift hari itu. Contoh: jadwal malam
     * 20:00-08:00 + lembur siang 14:00-17:00 -> scan 14:00/17:00 bukan masuk/pulang shift malam.
     * Scan yang juga jendela shift (mis. lembur menyambung jam masuk) tetap milik shift; scan pulang
     * shift malam kemarin tidak pernah dibuang.
     */
    private function withoutOvertimeScans(User $user, string $date, Collection $dayScans): Collection
    {
        $ranges = AttendanceRequestOvertimeDetails::query()
            ->where('date', $date)
            ->whereHas('attendanceRequest', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['in_review', 'approved']))
            ->get(['start_time', 'end_time'])
            ->map(function ($d) {
                $start = Carbon::parse($d->start_time);
                $end = Carbon::parse($d->end_time);

                return [
                    $start->copy()->subMinutes(self::OVERTIME_SCAN_TOLERANCE_MINUTES)->format('H:i:s'),
                    // Lembur lewat tengah malam: batas akhir dipotong di akhir hari (sisanya tanggal berikut).
                    $end->gt($start) ? $end->copy()->addMinutes(self::OVERTIME_SCAN_TOLERANCE_MINUTES)->format('H:i:s') : '23:59:59',
                ];
            });

        if ($ranges->isEmpty()) {
            return $dayScans;
        }

        $shiftConfigs = array_filter([$this->normalShiftConfigFor($user, $date), $this->find($user, $date)?->shiftType]);
        $prevConfig = $this->overnightConfigFor($user, Carbon::parse($date)->subDay()->toDateString());

        return $dayScans->reject(function ($scan) use ($ranges, $shiftConfigs, $prevConfig) {
            $t = $scan->access_time;

            if (! $ranges->contains(fn ($r) => $t >= $r[0] && $t <= $r[1])) {
                return false;
            }

            if ($prevConfig && $this->isOvernightCheckoutScan($t, $prevConfig)) {
                return false;
            }

            foreach ($shiftConfigs as $config) {
                if ($t >= $this->checkInWindowStart($config) && ($config->is_overnight || $t <= $this->checkOutWindowEnd($config))) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /** Termasuk overnight kalau jadwal NORMAL hari itu overnight, ATAU extra shift approved
     * hari itu overnight - dua-duanya berhak "minjem" scan pertama besok jadi check-out. */
    public function isOvernightShiftDay(User $user, string $date): bool
    {
        return $this->overnightConfigFor($user, $date) !== null;
    }

    /** Config shift lewat tengah malam yang berlaku di tanggal itu (normal, atau extra shift
     * approved), null kalau tidak ada. */
    private function overnightConfigFor(User $user, string $date): ?ScheduleShiftType
    {
        $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

        if ($resolved && in_array($resolved['type'], ['fixed', 'flexible'], true)) {
            return null;
        }

        if ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false) && ($resolved['config']->is_overnight ?? false)) {
            return $resolved['config'];
        }

        $extra = $this->find($user, $date)?->shiftType;

        return $extra && $extra->is_overnight ? $extra : null;
    }

    /**
     * Shift lewat tengah malam KEMARIN tanpa satu pun scan masuk (lupa absen), dan $scan (scan
     * pertama tanggal ini) jelas scan pulangnya: lebih dekat ke jam selesai shift itu daripada ke
     * jam mulainya, dan belum masuk jendela check-in jadwal tanggal ini sendiri. Hasil: entri
     * KEMARIN dengan check_in kosong + check_out = scan ini (tetap hadir, seperti Fixed). Null
     * kalau bukan kasus itu - scan tetap dibaca sebagai masuk tanggal ini.
     *
     * @param  array<int, string>  $producedDates  tanggal yang sudah punya entri di pairing ini
     */
    private function overnightCheckoutOnlyEntry(User $user, string $date, object $scan, array $producedDates): ?array
    {
        $prev = Carbon::parse($date)->subDay()->toDateString();

        if (in_array($prev, $producedDates, true)) {
            return null;
        }

        $prevConfig = $this->overnightConfigFor($user, $prev);

        if (! $prevConfig || ! $this->isOvernightCheckoutScan($scan->access_time, $prevConfig)) {
            return null;
        }

        $today = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);
        $todayConfigs = [
            ($today && ! ($today['is_off_day'] ?? false)) ? ($today['config'] ?? null) : null,
            $this->find($user, $date)?->shiftType,
        ];

        foreach ($todayConfigs as $config) {
            if ($config && ! $this->isBeforeCheckInWindow($scan->access_time, $config)) {
                return null;
            }
        }

        // Normal + extra sama2 ada kemarin: milik extra hanya kalau shift malam itu memang shift
        // extra-nya, selain itu pulang shift normal.
        $prevExtra = $this->find($user, $prev);
        $ownedByExtra = $prevExtra && $prevConfig->getKey() === $prevExtra->shiftType->getKey();

        $entry = [
            'employee_id'  => $user->nip,
            'date'         => $prev,
            'check_in'     => null,
            'check_out'    => $scan->access_time,
            'scan_count'   => 1,
            'device_names' => array_values(array_filter([$scan->device_name ?? null])),
            'user'         => $user,
            'owner'        => $ownedByExtra ? 'extra' : 'normal',
        ];

        return $ownedByExtra
            ? $entry + ['config' => $prevExtra->shiftType, 'extra_shift_assignment_id' => $prevExtra->id]
            : $entry;
    }

    /** Jadwal SHIFT normal (bukan off_day, bukan fixed/flexible) user di tanggal itu. Null kalau
     * hari itu libur/kosong atau jadwal user bukan tipe shift. */
    private function normalShiftConfigFor(User $user, string $date): ?ScheduleShiftType
    {
        $resolved = $this->scheduleResolver->resolveForUserDate($user, $date, ignoreEffectiveDate: true);

        if ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false)) {
            return $resolved['config'] ?? null;
        }

        return null;
    }

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

        return $this->tagOwners(
            $this->pairEntries($user, $date, $dayScans, $normalConfig, $extraShift, $byDate, $consumedFirstScan),
            $extraShift,
        );
    }

    /**
     * Setiap entri hasil pairing diberi pemilik EKSPLISIT: 'normal' (pasangan shift jadwal) atau
     * 'extra' (+ config & id penugasannya). Penanda internal extra_shift_handled berhenti di sini -
     * pembaca di atasnya tidak perlu menebak lagi lewat routing ulang.
     *
     * @param  array<int, array>  $entries
     * @return array<int, array>
     */
    private function tagOwners(array $entries, ?ExtraShiftAssignment $extraShift): array
    {
        return array_map(function (array $entry) use ($extraShift) {
            $isNormal = ! $extraShift || ! empty($entry['extra_shift_handled']);
            unset($entry['extra_shift_handled']);

            return $isNormal
                ? $entry + ['owner' => 'normal']
                : $entry + [
                    'owner' => 'extra',
                    'config' => $extraShift->shiftType,
                    'extra_shift_assignment_id' => $extraShift->id,
                ];
        }, $entries);
    }

    /**
     * Pasangan extra shift beneran milik extra: jam masuknya sudah di jendela check-in shift extra
     * (atau cuma ada jam pulang - lupa scan masuk). Kalau tidak, check_in yang kebawa sebenarnya jam
     * masuk shift NORMAL - pembaca di atas menjatuhkannya ke jadwal normal hari itu.
     */
    public function isGenuineExtraEntry(array $entry): bool
    {
        if (! $entry['check_in']) {
            return filled($entry['check_out'] ?? null);
        }

        $earliestCheckIn = Carbon::createFromFormat('H:i:s', $entry['config']->start_time)
            ->subMinutes($entry['config']->checkin_buffer_minutes);

        return Carbon::createFromFormat('H:i:s', $entry['check_in'])->gte($earliestCheckIn);
    }

    private function pairEntries(User $user, string $date, Collection $dayScans, ?ScheduleShiftType $normalConfig, ?ExtraShiftAssignment $extraShift, Collection $byDate, array &$consumedFirstScan): array
    {
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

            $entry = $this->buildEntryFromScans($user, $date, $dayScans, $byDate, $consumedFirstScan, (bool) $owner->is_overnight, $owner);

            if ($entry && ! $extraOwns) {
                $entry['extra_shift_handled'] = true;
            }

            return $entry ? [$entry] : [];
        }

        // Tidak ada extra shift (langsung normal), ATAU libur/kosong + extra (pasangan milik extra
        // shift, jangan ditandai extra_shift_handled). "Pinjam scan besok" (overnight) boleh kalau
        // shift yang berlaku hari itu overnight.
        $allowNextDayBorrow = ($normalConfig?->is_overnight ?? false) || ($extraShift?->shiftType?->is_overnight ?? false);
        $entry = $this->buildEntryFromScans($user, $date, $dayScans, $byDate, $consumedFirstScan, $allowNextDayBorrow, $normalConfig ?? $extraShift?->shiftType);

        return $entry ? [$entry] : [];
    }

    /**
     * Scan tunggal yang waktunya sudah >= jam pulang shift (non-overnight, bukan Oncall) = scan
     * pulang; karyawan lupa/lewat scan masuk. Dicatat hadir dengan check_out saja (check_in kosong),
     * sama seperti presensi Fixed (AccessLogScheduleReader::singleScanIsCheckout()).
     */
    public function isCheckoutOnlyScan(string $scanTime, ?ScheduleShiftType $config): bool
    {
        return $config
            && ! $config->is_overnight
            && $config->shift_type !== ShiftType::ONCALL->value
            && $config->end_time > $config->start_time
            && $scanTime >= $config->end_time;
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

        // Scan di titik transisi (mis. 14:00 untuk Pagi 08-14 + Siang 14-20) ditolak - bukan pulang
        // shift awal, perhitungan langsung lanjut ke shift kedua. Scan pertama selalu dipakai.
        $from = $this->checkInWindowStart($later);
        $to = $this->checkOutWindowEnd($earlier);
        $beforeFilter = $scans->count();
        $scans = $scans->filter(fn ($s, $i) => $i === 0 || $s->access_time < $from || $s->access_time > $to)->values();
        $transitionDropped = $scans->count() < $beforeFilter;

        // Scan tunggal sudah lewat jam pulang shift kedua = pulang shift kedua saja (lupa scan masuk).
        // Shift awal tidak ada bukti dikerjakan, jadi tidak dibuat entrinya.
        if ($scans->count() === 1 && $this->isCheckoutOnlyScan($scans->first()->access_time, $later)) {
            $only = $this->buildEntryFromScans($user, $date, $scans, $byDate, $consumedFirstScan, false, $later);

            return [array_merge($only, $earlierIsNormal ? [] : ['extra_shift_handled' => true])];
        }

        $whole = $this->buildEntryFromScans($user, $date, $scans, $byDate, $consumedFirstScan, allowNextDayBorrow: (bool) $later->is_overnight);
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
        $entry = $make($in, $out, $ownerIsNormal);

        // Scan terakhir jatuh di titik transisi & tidak ada scan sesudahnya: itu pulang shift awal,
        // extra tidak dikerjakan. Jam pulang = jam selesai shift awal (sintetis), sama seperti
        // pasangan menyambung lainnya.
        if ($transitionDropped && $out === null && $earlierWorked) {
            $entry['check_out'] = $earlier->end_time;
        }

        return [$entry];
    }

    // ponytail: string compare jam "H:i:s" - salah kalau shift mulai < buffer menit dari tengah malam
    // (wrap ke hari sebelumnya), belum ada shift seperti itu. Tangani kalau muncul.
    private function checkInWindowStart(ScheduleShiftType $config): string
    {
        return Carbon::createFromFormat('H:i:s', $config->start_time)->subMinutes($config->checkin_buffer_minutes)->format('H:i:s');
    }

    /** Scan sebelum jendela check-in config (jadi belum bisa jadi jam masuk shift itu). Config
     * tanpa start_time dianggap bentrok (false) - scan tidak diambil alih. */
    public function isBeforeCheckInWindow(string $scanTime, object $config): bool
    {
        if (! isset($config->start_time)) {
            return false;
        }

        return $scanTime < Carbon::createFromFormat('H:i:s', $config->start_time)
            ->subMinutes((int) ($config->checkin_buffer_minutes ?? 0))->format('H:i:s');
    }

    /** Scan pagi pasca shift lewat tengah malam: lebih dekat ke jam selesai daripada ke jam
     * mulai shift (titik tengah). Scan telat beberapa menit dari jam selesai tetap termasuk -
     * tidak bergantung checkout_buffer_minutes. */
    public function isOvernightCheckoutScan(string $scanTime, ScheduleShiftType $config): bool
    {
        $end = Carbon::createFromFormat('H:i:s', $config->end_time);
        $start = Carbon::createFromFormat('H:i:s', $config->start_time);

        if ($end->gte($start)) {
            return false;
        }

        $mid = $end->copy()->addSeconds((int) ($end->diffInSeconds($start) / 2))->format('H:i:s');

        return $scanTime <= $mid;
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

        // Bandingkan H:i (DB bisa "14:00" vs "14:00:00"), dan jam overlap (selesai >= mulai) juga
        // dianggap nempel - bukan cuma sama persis.
        // ponytail: shift overnight sebagai "earlier" tidak ditangani (end < start), tambah kalau ada kasusnya.
        return substr($earlierConfig->end_time, 0, 5) >= substr($laterConfig->start_time, 0, 5);
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
    public function buildEntryFromScans(User $user, string $date, Collection $scans, Collection $byDate, array &$consumedFirstScan, bool $allowNextDayBorrow, ?ScheduleShiftType $owner = null): ?array
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
        } elseif ($this->isCheckoutOnlyScan($checkIn, $owner)) {
            $checkOut = $checkIn;
            $checkIn  = null;
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

}
