<?php

namespace App\Services\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Attendance\AttendanceSettingEnum;
use App\Models\Setting;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShiftSwapRequestService
{
    /** Setting key (App\Models\Setting::resolve()) - kalau ON, target dinotif & bisa kasih
     * tanggapan Setuju/Tolak lewat App\Livewire\Presensi\ShiftSwapTargetResponseCard, TAPI ini
     * murni informasi buat approver, TIDAK PERNAH menggerbang/menunda alur approval (lihat
     * planning/2026-08-16-fitur-tukar-shift.md §A2 - instruksi user: opsional, tidak wajib).
     * Sengaja bukan case App\Enum\Attendance\AttendanceSettingEnum - enum itu auto-tampil di
     * AttendanceSettingsPage (iterasi ::cases() generik), toggle ini belum siap diekspos ke UI
     * admin, dinyalakan manual dulu lewat baris Setting langsung. */
    public const ASK_TARGET_RESPONSE_SETTING_KEY = 'ask_target_response_tukar_shift';

    public function __construct(
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
    ) {}

    public function store(array $data): ShiftSwapRequests
    {
        $user = Auth::user();

        $reqSchedule = ScheduleShiftAssignment::with('shiftType')->findOrFail($data['req_schedule_id']);

        $this->assertWithinSettingLimits($reqSchedule, $user);

        return $this->storeScheduleSwap($data, $reqSchedule, $user);
    }

    /**
     * @param array{req_schedule_id:string, target_schedule_id:string, swap_kind?:string, notes?:?string} $data
     *   `swap_kind` (default 'schedule'): 'reciprocal_off' = tukar libur berpasangan (rekan
     *   sedang Libur di tanggal $reqSchedule, pemohon "membayar" balik dengan mengambil alih
     *   shift KERJA rekan di tanggal lain) - lihat assertReciprocalOffEligible() & planning/
     *   2026-09-12-tukar-shift-libur-berpasangan.md. Konflik nyata pada kind ini diblok KERAS
     *   (bukan warning-only seperti 'schedule' biasa, lihat resolveSlot()/conflictWarnings()).
     */
    private function storeScheduleSwap(
        array $data,
        ScheduleShiftAssignment $reqSchedule,
        $user,
    ): ShiftSwapRequests {
        $targetSchedule = ScheduleShiftAssignment::with('shiftType')->findOrFail($data['target_schedule_id']);
        $swapKind = $data['swap_kind'] ?? 'schedule';

        $this->validateScheduleSwapRules($reqSchedule, $targetSchedule);
        $this->assertNoPendingSwapOnSlots([
            [$reqSchedule->user_id, $reqSchedule->date],
            [$targetSchedule->user_id, $targetSchedule->date],
        ]);

        if ($swapKind === 'reciprocal_off') {
            $this->assertReciprocalOffEligible($reqSchedule, $targetSchedule);
            // Gagal-cepat kalau salah satu sisi TERNYATA sudah punya shift lain beneran (bukan
            // Libur) - hasilnya dibuang, cuma buat validasi saat submit (throw sendiri kalau
            // ada konflik nyata). Eksekusi beneran & re-cek final tetap di executeApprovedSwap().
            $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule, strict: true);
        } elseif (! $reqSchedule->date->equalTo($targetSchedule->date)) {
            // swap_kind 'schedule' (default) beda tanggal: read-only, non-strict (double shift
            // beda TIPE tetap boleh submit, cuma warning-only seperti biasa - lihat
            // conflictWarningsForSchedules()) - tapi resolveSlot() sendiri tetap throw kalau
            // ketemu kasus DUPLIKAT (busy-nya persis shift yang sama tipenya) apa pun nilai
            // strict, jadi baris ini gagal-cepat blokir kasus itu saat submit, bukan nunggu
            // meledak pas approval final dieksekusi (instruksi user 2026-09-16). Hasilnya dibuang -
            // cuma buat efek sampingnya (throw), eksekusi beneran tetap di executeApprovedSwap().
            $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule, strict: false);
        }

        return DB::transaction(function () use ($data, $reqSchedule, $targetSchedule, $user, $swapKind) {
            $swapRequest = ShiftSwapRequests::create([
                'company_id'         => $user->company_id,
                'requester_id'       => $user->id,
                'target_id'          => $targetSchedule->user_id,
                'req_schedule_id'    => $reqSchedule->id,
                'target_schedule_id' => $targetSchedule->id,
                'swap_kind'          => $swapKind,
                'req_date'           => $reqSchedule->date,
                'target_date'        => $targetSchedule->date,
                'status'             => 'in_review',
                'notes'              => $data['notes'] ?? null,
            ]);

            $this->createApprovalSnapshot($swapRequest, $user);

            return $swapRequest;
        });
    }

    /**
     * Syarat tukar libur berpasangan (swap_kind='reciprocal_off') - lihat planning/
     * 2026-09-12-tukar-shift-libur-berpasangan.md §0/§3:
     * - $reqSchedule (shift pemohon, diserahkan) dan $targetSchedule (shift rekan, diambil alih
     *   pemohon sebagai kompensasi) TIDAK BOLEH tanggal yang sama.
     * - Rekan WAJIB benar-benar Libur (baris schedule_shift_assignments ADA, off_day=true) pada
     *   tanggal $reqSchedule - N/A/kosong (tidak ada baris sama sekali) TIDAK CUKUP, sengaja
     *   query LANGSUNG ke tabel (bukan ScheduleResolver) supaya proyeksi pola fixed/flexible
     *   tidak ikut kehitung sebagai "Libur".
     * - $targetSchedule (tanggal kompensasi) HARUS shift KERJA rekan beneran (shift_type_id
     *   terisi, off_day=false) - bukan libur rekan juga.
     * - Tanggal kompensasi itu JUGA WAJIB tanggal LIBUR pemohon sendiri (off_day=true) - instruksi
     *   user 2026-09-14: pemohon "membayar" pakai jatah liburnya sendiri (bukan sekadar tanggal
     *   kosong mana pun yang kebetulan tidak bentrok) - simetris sama syarat rekan di atas. Baris
     *   Libur pemohon ini nanti DITIMPA pas eksekusi (lihat applySlotResolution() mode
     *   'move_over_off_day' - baris Libur bukan konflik beneran, boleh dihapus & digantikan shift
     *   rekan yang dioper).
     */
    private function assertReciprocalOffEligible(
        ScheduleShiftAssignment $reqSchedule,
        ScheduleShiftAssignment $targetSchedule,
    ): void {
        if ($reqSchedule->date->equalTo($targetSchedule->date)) {
            throw new \InvalidArgumentException(
                'Tanggal kompensasi tidak boleh sama dengan tanggal shift yang diserahkan.'
            );
        }

        $targetIsOffOnReqDate = ScheduleShiftAssignment::where('user_id', $targetSchedule->user_id)
            ->whereDate('date', $reqSchedule->date)
            ->where('off_day', true)
            ->exists();

        if (! $targetIsOffOnReqDate) {
            throw new \InvalidArgumentException(
                'Rekan tujuan harus benar-benar Libur (bukan kosong/tanpa jadwal) pada tanggal shift yang diserahkan.'
            );
        }

        if ($targetSchedule->off_day || ! $targetSchedule->shift_type_id) {
            throw new \InvalidArgumentException(
                'Tanggal kompensasi harus tanggal shift KERJA rekan, bukan libur rekan.'
            );
        }

        $requesterIsOffOnCompensationDate = ScheduleShiftAssignment::where('user_id', $reqSchedule->user_id)
            ->whereDate('date', $targetSchedule->date)
            ->where('off_day', true)
            ->exists();

        if (! $requesterIsOffOnCompensationDate) {
            throw new \InvalidArgumentException(
                'Tanggal kompensasi harus tanggal Libur Anda sendiri (bukan kosong/tanpa jadwal).'
            );
        }
    }

    /** Saldo pengajuan tukar shift milik $user dalam PERIODE ABSENSI (AttendanceSettingEnum::PERIOD_*)
     * yang memuat $anchorDate - bukan bulan kalender. null = tidak dibatasi
     * (TUKAR_SHIFT_MAX_PER_BULAN <= 0). Pengajuan ditolak/dibatalkan tidak dihitung.
     *
     * @return array{max:int, used:int, remaining:int, start:\Carbon\Carbon, end:\Carbon\Carbon}|null
     */
    public function swapQuota(User $user, \Carbon\Carbon $anchorDate): ?array
    {
        $max = (int) Setting::resolve(AttendanceSettingEnum::TUKAR_SHIFT_MAX_PER_BULAN->value, $user->company_id, 0);
        if ($max <= 0) {
            return null;
        }

        [$start, $end] = AttendancePeriodResolver::resolve($anchorDate->copy(), $user->company_id);

        $used = ShiftSwapRequests::where('requester_id', $user->id)
            ->whereIn('status', ['in_review', 'approved'])
            ->whereBetween('req_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        return [
            'max' => $max,
            'used' => $used,
            'remaining' => max(0, $max - $used),
            'start' => $start,
            'end' => $end,
        ];
    }

    /** Batas dari Pengaturan Absensi (company-scoped) - lihat swapQuota(). Jarak minimal hari
     * pengajuan (dulu TUKAR_SHIFT_MIN_LEAD_DAYS) SENGAJA dimatikan total (instruksi user
     * 2026-09-16) - tanggal shift paling cepat yang boleh diajukan sekarang cuma "hari ini,
     * tidak boleh sebelumnya", dijaga di form (ShiftSwapRequestModal::getMinDateProperty()),
     * bukan aturan company-scoped lagi di sini. */
    private function assertWithinSettingLimits(ScheduleShiftAssignment $reqSchedule, $user): void
    {
        $quota = $this->swapQuota($user, $reqSchedule->date);
        if ($quota && $quota['used'] >= $quota['max']) {
            throw new \InvalidArgumentException(
                "Saldo tukar shift Anda habis (maksimal {$quota['max']} pengajuan) untuk periode " .
                $quota['start']->translatedFormat('d M') . ' - ' . $quota['end']->translatedFormat('d M Y') . '.'
            );
        }
    }

    /** Blokir pengajuan kalau salah satu (user, tanggal) yang mau dilibatkan SUDAH nyangkut di
     * pengajuan tukar shift lain yang masih `in_review` - biar tidak "mengganggu" / menimpa proses
     * approval rekan yang sedang jalan. Begitu pengajuan lama itu selesai (approved/rejected/
     * cancelled), slot-nya bebas lagi.
     *
     * @param array<int, array{0: string, 1: \Carbon\CarbonInterface|string}> $slots [user_id, tanggal]
     */
    private function assertNoPendingSwapOnSlots(array $slots): void
    {
        foreach ($slots as [$userId, $date]) {
            $date = $date instanceof \Carbon\CarbonInterface ? $date->toDateString() : (string) $date;

            $busy = ShiftSwapRequests::where('status', 'in_review')
                ->where(function ($q) use ($userId, $date) {
                    $q->where(fn ($qq) => $qq->where('requester_id', $userId)->whereDate('req_date', $date))
                        ->orWhere(fn ($qq) => $qq->where('target_id', $userId)->whereDate('target_date', $date));
                })
                ->exists();

            if ($busy) {
                throw new \InvalidArgumentException(
                    'Salah satu jadwal yang terlibat sedang dalam proses pengajuan tukar shift lain. '
                    . 'Tunggu pengajuan itu selesai (disetujui atau ditolak) dulu.'
                );
            }
        }
    }

    private function validateScheduleSwapRules(
        ScheduleShiftAssignment $reqSchedule,
        ScheduleShiftAssignment $targetSchedule,
    ): void {
        // Same calendar month & year
        if ($reqSchedule->date->format('Y-m') !== $targetSchedule->date->format('Y-m')) {
            throw new \InvalidArgumentException(
                'Tukar shift hanya berlaku dalam bulan yang sama.'
            );
        }

        // shift_type compatibility (pagi ↔ siang diperbolehkan, malam hanya dengan malam) CUMA
        // berlaku buat tukar BEDA tanggal (instruksi user 2026-08-20) - tanggal sama cuma nuker
        // kategori shift di hari yang sama, tidak menggeser hari kerja siapa pun, jadi bebas mau
        // kombinasi apa saja asal kedua belah pihak setuju.
        if ($reqSchedule->date->equalTo($targetSchedule->date)) {
            return;
        }

        $reqType    = $reqSchedule->shiftType?->shift_type;
        $targetType = $targetSchedule->shiftType?->shift_type;

        $pagiSiang = [\App\Enum\ShiftType::PAGI->value, \App\Enum\ShiftType::SIANG->value];
        $compatible = (in_array($reqType, $pagiSiang) && in_array($targetType, $pagiSiang))
            || ($reqType === \App\Enum\ShiftType::MALAM->value && $targetType === \App\Enum\ShiftType::MALAM->value);

        if (!$reqType || !$targetType || !$compatible) {
            $reqLabel    = $reqType    ?? 'tidak diketahui';
            $targetLabel = $targetType ?? 'tidak diketahui';
            throw new \InvalidArgumentException(
                "Tidak dapat menukar shift {$reqLabel} dengan shift {$targetLabel}. " .
                'Pagi dan siang dapat saling ditukar. Shift malam hanya bisa ditukar dengan sesama malam.'
            );
        }
    }

    /** Dipanggil dari ApprovalFlowService pas swap request disetujui final - satu-satunya tempat
     * yang benar-benar menulis perubahan ke schedule_shift_assignments (bukan pas diajukan).
     * Tanggal sama -> tukar shift_type_id (hindari bentrok unique (user_id,date)). Tanggal beda ->
     * re-cek konflik (bisa berubah sejak pengajuan) lalu jalankan hasilnya: slot yang "move"
     * pindah user_id seperti biasa, slot yang "move_over_off_day" (busy-nya cuma baris Libur,
     * bukan konflik beneran) ditimpa, slot yang "double_shift" (busy-nya shift KERJA beneran)
     * ikut `swap_kind`: 'schedule' (default) TETAP DILAKSANAKAN - shift yang dioper dicatat
     * sebagai ExtraShiftAssignment category=double_shift (tanpa jasa) di atas shift asli
     * incoming owner, lihat applySlotResolution() (instruksi user 2026-09-13, sebelumnya
     * no-op/warning-only); 'reciprocal_off' (tukar libur berpasangan) malah harus GAGAL total
     * (exception naik ke ApprovalFlowService::advanceStep, approval tidak lanjut) - supaya tidak
     * ada sisi yang partial-execute (pemohon untung, rekan tidak jadi dapat libur balik). Lihat
     * planning/2026-09-12-tukar-shift-libur-berpasangan.md.
     *
     * Khusus 'reciprocal_off': tanggal yang DILEPAS masing-masing pihak (reqDate buat pemohon,
     * targetDate buat rekan) SENGAJA ditandai Libur eksplisit (off_day=true), BEDA dari mode
     * 'schedule' biasa yang dibiarkan kosong/N-A (instruksi user 2026-09-14 - ini memang
     * "pertukaran libur", jadi baik pemohon maupun rekan harus benar-benar dapat baris Libur
     * balik, bukan cuma "tidak ada jadwal"). Aman dibuat baris baru (bukan update) karena baris
     * lama pemilik itu di tanggal tsb sudah 100% lenyap lewat applySlotResolution() (mode 'move'
     * mengubah kepemilikan baris yang SAMA, mode 'move_over_off_day' menghapus baris Libur milik
     * incoming owner di tanggal ini) - tidak ada baris tersisa yang bisa bentrok unique
     * (user_id,date). */
    public function executeApprovedSwap(ShiftSwapRequests $swapRequest): void
    {
        $swapRequest->loadMissing(['reqSchedule.shiftType', 'targetSchedule.shiftType', 'requester', 'target']);

        $reqSchedule = $swapRequest->reqSchedule;
        $targetSchedule = $swapRequest->targetSchedule;

        if (! $reqSchedule || ! $targetSchedule) {
            return;
        }

        if ($reqSchedule->date->equalTo($targetSchedule->date)) {
            $reqShiftTypeId = $reqSchedule->shift_type_id;
            $targetShiftTypeId = $targetSchedule->shift_type_id;
            $reqSchedule->update(['shift_type_id' => $targetShiftTypeId]);
            $targetSchedule->update(['shift_type_id' => $reqShiftTypeId]);

            return;
        }

        $isReciprocalOff = $swapRequest->swap_kind === 'reciprocal_off';
        $plan = $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule, strict: $isReciprocalOff);

        $reqUserId = $reqSchedule->user_id;
        $targetUserId = $targetSchedule->user_id;
        $reqDate = $reqSchedule->date->toDateString();
        $targetDate = $targetSchedule->date->toDateString();
        $companyId = $reqSchedule->company_id;

        $this->applySlotResolution($plan['req_date'], $reqSchedule, $targetUserId, $swapRequest);
        $this->applySlotResolution($plan['target_date'], $targetSchedule, $reqUserId, $swapRequest);

        if ($isReciprocalOff) {
            ScheduleShiftAssignment::create([
                'company_id' => $companyId,
                'user_id' => $reqUserId,
                'date' => $reqDate,
                'off_day' => true,
                'shift_type_id' => null,
            ]);

            ScheduleShiftAssignment::create([
                'company_id' => $companyId,
                'user_id' => $targetUserId,
                'date' => $targetDate,
                'off_day' => true,
                'shift_type_id' => null,
            ]);
        }
    }

    /** @param array{mode: 'move'|'move_over_off_day'|'double_shift', busy?: ScheduleShiftAssignment, warning?: string} $slot */
    private function applySlotResolution(array $slot, ScheduleShiftAssignment $originalSchedule, string $incomingUserId, ShiftSwapRequests $swapRequest): void
    {
        if ($slot['mode'] === 'move') {
            $originalSchedule->update(['user_id' => $incomingUserId]);

            return;
        }

        if ($slot['mode'] === 'move_over_off_day') {
            // Baris "busy" cuma penanda Libur (off_day=true), bukan konflik beneran - boleh
            // ditimpa: hapus penandanya, baris asal pindah kepemilikan seperti mode 'move'.
            $slot['busy']->delete();
            $originalSchedule->update(['user_id' => $incomingUserId]);

            return;
        }

        if ($slot['mode'] === 'double_shift') {
            // $incomingUserId TERNYATA sudah punya shift lain ($slot['busy']) di tanggal ini -
            // tukar tetap dilaksanakan sesuai niat awal (pemilik asal benar2 lepas), tapi shift
            // yang dioper TIDAK bisa jadi baris ke-2 schedule_shift_assignments (unique
            // user_id+date) - dicatat sebagai ExtraShiftAssignment category=double_shift (BUKAN
            // extra_shift biasa, TIDAK kena jasa ExtraShiftRule - ini konsekuensi tukar, bukan
            // penugasan sukarela). Baris asal dihapus - pemiliknya lama benar2 lepas dari shift
            // itu, sama seperti mode 'move' (instruksi user 2026-09-13).
            ExtraShiftAssignment::create([
                'company_id' => $originalSchedule->company_id,
                'user_id' => $incomingUserId,
                'shift_type_id' => $originalSchedule->shift_type_id,
                'date' => $originalSchedule->date,
                'status' => 'approved',
                'source' => 'swap_conflict',
                'category' => ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT,
                'origin_shift_swap_request_id' => $swapRequest->id,
                'created_by' => Auth::id(),
            ]);

            $originalSchedule->delete();
        }
    }

    /** Tentukan siapa yang akhirnya nempatin baris req_schedule (tanggal pemohon) dan baris
     * target_schedule (tanggal rekan) buat kasus tukar BEDA tanggal. Kalau salah satu pihak
     * TERNYATA sudah punya baris lain di tanggal yang dituju: baris Libur (off_day=true) boleh
     * ditimpa (mode 'move_over_off_day', bukan konflik beneran); baris shift KERJA beneran jadi
     * mode 'double_shift' - dengan `$strict=false` (default) TETAP DILAKSANAKAN sebagai double
     * shift tanpa jasa (instruksi user 2026-09-13); dengan `$strict=true` (dipakai
     * swap_kind='reciprocal_off') malah throw supaya submit/approval gagal total, lihat
     * resolveSlot(). Read-only, aman dipanggil berkali-kali: sekali pas eksekusi approval final
     * (executeApprovedSwap, hasilnya dipakai), berkali-kali lagi buat validasi pra-submit
     * reciprocal_off & preview warning (conflictWarnings/conflictWarningsForSchedules) di form
     * pengajuan & halaman detail - supaya perubahan jadwal di antara momen-momen itu tetap
     * ke-cover ulang.
     *
     * @return array{req_date: array{mode:string,busy?:ScheduleShiftAssignment,warning?:string}, target_date: array{mode:string,busy?:ScheduleShiftAssignment,warning?:string}}
     */
    private function resolveDifferentDateExecution(
        ScheduleShiftAssignment $reqSchedule,
        ScheduleShiftAssignment $targetSchedule,
        bool $strict = false,
    ): array {
        $targetConflict = ScheduleShiftAssignment::with('shiftType')
            ->where('user_id', $targetSchedule->user_id)
            ->whereDate('date', $reqSchedule->date)
            ->whereNotIn('id', [$reqSchedule->id, $targetSchedule->id])
            ->first();

        $reqConflict = ScheduleShiftAssignment::with('shiftType')
            ->where('user_id', $reqSchedule->user_id)
            ->whereDate('date', $targetSchedule->date)
            ->whereNotIn('id', [$reqSchedule->id, $targetSchedule->id])
            ->first();

        return [
            'req_date' => $this->resolveSlot($targetConflict, $targetSchedule->user, 'Karyawan tujuan sudah memiliki jadwal lain pada tanggal shift Anda', $strict, $reqSchedule->shift_type_id),
            'target_date' => $this->resolveSlot($reqConflict, $reqSchedule->user, 'Anda sudah memiliki jadwal lain pada tanggal shift tujuan', $strict, $targetSchedule->shift_type_id),
        ];
    }

    /** @return array{mode: 'move'|'move_over_off_day'|'double_shift', busy?: ScheduleShiftAssignment, warning?: string}
     * @param string|null $incomingShiftTypeId shift_type_id yang lagi DIOPER ke slot ini - dipakai
     *   buat cek kasus "duplikat" di bawah. */
    private function resolveSlot(?ScheduleShiftAssignment $busy, ?User $incomingOwner, string $conflictMessagePrefix, bool $strict = false, ?string $incomingShiftTypeId = null): array
    {
        if (! $busy) {
            return ['mode' => 'move'];
        }

        if ($busy->off_day) {
            // Cuma baris Libur (penanda), bukan konflik beneran - boleh ditimpa siapa pun,
            // termasuk jalur non-strict.
            return ['mode' => 'move_over_off_day', 'busy' => $busy];
        }

        $busyShift = $busy->shiftType?->name ?? 'shift lain';

        // Shift yang DIOPER persis SAMA tipenya dengan shift yang busy-nya udah py di tanggal itu
        // (independen dari aturan kompatibilitas pagi/siang/malam di validateScheduleSwapRules() -
        // itu cuma ngecek reqSchedule vs targetSchedule, sedangkan $busy ini baris LAIN yang
        // kebetulan sudah ada) - bukan double shift yang masuk akal (rekan/pemohon sudah otomatis
        // "kerja shift itu"), jadi SELALU diblokir keras apa pun nilai $strict-nya, baik swap_kind
        // 'schedule' maupun 'reciprocal_off' (instruksi user 2026-09-16).
        if ($incomingShiftTypeId && $busy->shift_type_id === $incomingShiftTypeId) {
            $name = $incomingOwner?->name ?? 'Karyawan';

            throw new \InvalidArgumentException(
                "{$conflictMessagePrefix} dengan shift yang SAMA ({$busyShift}) - {$name} sudah otomatis kerja shift itu, tidak bisa ditukarkan"
            );
        }

        if ($strict) {
            throw new \InvalidArgumentException(
                "{$conflictMessagePrefix} ({$busyShift}) - tukar libur berpasangan butuh tanggal ini benar-benar Libur/kosong di kedua belah pihak."
            );
        }

        $name = $incomingOwner?->name ?? 'Karyawan';

        return [
            'mode' => 'double_shift',
            'busy' => $busy,
            'warning' => "{$conflictMessagePrefix} ({$busyShift}) - {$name} akan bekerja double shift (shift ini ditambahkan di atas shift {$busyShift} yang sudah ada).",
        ];
    }

    /** Peringatan double shift buat pengajuan yang BELUM disimpan (preview di form pengajuan) -
     * read-only, tidak menyentuh DB. [] kalau tidak ada konflik atau tukar tanggal sama
     * (kompatibilitas shift_type dicek terpisah di validateScheduleSwapRules). Kasus DUPLIKAT
     * (shift yang dioper persis sama tipenya, lihat resolveSlot()) SENGAJA tidak nongol di sini -
     * itu bukan warning tapi hard block, lihat duplicateShiftBlock(). */
    public function conflictWarningsForSchedules(?ScheduleShiftAssignment $reqSchedule, ?ScheduleShiftAssignment $targetSchedule): array
    {
        if (! $reqSchedule || ! $targetSchedule || $reqSchedule->date->equalTo($targetSchedule->date)) {
            return [];
        }

        try {
            $plan = $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule);
        } catch (\InvalidArgumentException) {
            // Kasus duplikat (resolveSlot() throw) - biar duplicateShiftBlock() yang nampilin
            // pesannya sebagai blocker, di sini cukup diam (tidak ada warning yang relevan).
            return [];
        }

        return array_values(array_filter([
            $plan['req_date']['warning'] ?? null,
            $plan['target_date']['warning'] ?? null,
        ]));
    }

    /** Cek KHUSUS kasus shift yang DIOPER persis SAMA tipenya dengan shift yang sudah dipunya
     * rekan/pemohon di tanggal tujuan (lihat resolveSlot()) - beda dari
     * conflictWarningsForSchedules() yang warning-only & tetap boleh submit, kasus ini SELALU
     * hard block (preview MAUPUN submit, lihat storeScheduleSwap()) karena hasilnya tidak masuk
     * akal - "kerja shift yang sama 2x" (instruksi user 2026-09-16). null kalau aman/tidak ada
     * kasus ini. Dipakai modal pengajuan buat nampilin keterangan & nge-disable tombol Ajukan
     * SEBELUM user coba submit, bukan cuma nunggu gagal. */
    public function duplicateShiftBlock(?ScheduleShiftAssignment $reqSchedule, ?ScheduleShiftAssignment $targetSchedule): ?string
    {
        if (! $reqSchedule || ! $targetSchedule || $reqSchedule->date->equalTo($targetSchedule->date)) {
            return null;
        }

        try {
            $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** Sama seperti conflictWarningsForSchedules(), tapi untuk pengajuan yang SUDAH tersimpan
     * (dipakai halaman detail) - dihitung ulang live tiap dipanggil, bukan snapshot saat submit,
     * supaya tetap akurat kalau jadwal berubah setelah pengajuan dibuat. */
    public function conflictWarnings(ShiftSwapRequests $request): array
    {
        $request->loadMissing(['reqSchedule.shiftType', 'targetSchedule.shiftType']);

        return $this->conflictWarningsForSchedules($request->reqSchedule, $request->targetSchedule);
    }

    /** Peringatan double shift KHUSUS sisi REKAN (target) - dipakai ShiftSwapApprovalsCard
     * (widget dashboard rekan) supaya dia tahu SEBELUM approve kalau menyetujui bakal bikin dia
     * double shift di tanggal shift pemohon (reqDate). Beda dari conflictWarnings() yang generik
     * 2 arah (peduli konflik di kedua pihak, dipakai pengaju di modal pengajuan & halaman
     * Detail) - di sini cuma ambil separuh 'req_date' dari hasilnya, yang selalu soal jadwal
     * REKAN (lihat resolveDifferentDateExecution()). null kalau tidak ada konflik/tanggal sama. */
    public function targetDoubleShiftWarning(ShiftSwapRequests $request): ?string
    {
        $request->loadMissing(['reqSchedule.shiftType', 'targetSchedule.shiftType']);

        $reqSchedule = $request->reqSchedule;
        $targetSchedule = $request->targetSchedule;

        if (! $reqSchedule || ! $targetSchedule || $reqSchedule->date->equalTo($targetSchedule->date)) {
            return null;
        }

        return $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule)['req_date']['warning'] ?? null;
    }

    private function createApprovalSnapshot(ShiftSwapRequests $swapRequest, $user): void
    {
        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
            companyId:  $user->company_id,
            userId:     $user->id,
        );

        if (!$masterFlow) {
            throw new \RuntimeException(
                'Tidak ada alur persetujuan aktif untuk tukar shift di perusahaan ini.'
            );
        }

        // Urutan approver yang disisipkan di depan master flow (instruksi user 2026-08-28):
        //   1. Rekan (target_id) - approver pertama sungguhan, seperti sejak 2026-08-20.
        //   2. Kepala Ruangan jadwal shift yang diajukan (Schedule::head_user_id) - titik approval
        //      yang pasti untuk 1 jadwal, karena isinya bisa banyak jabatan dengan atasan hirarki
        //      beda-beda (mis. perawat orientasi di tiap ruangan).
        // Lalu baru step-step master flow modul Tukar Shift (atasan, dst).
        //
        // Kalau jadwal BELUM punya Kepala Ruangan -> pengajuan DIBLOKIR di sini (bukan dilewati
        // diam-diam): snapshot approval sekali dibuat belum bisa dihapus/dibatalkan lewat portal,
        // jadi lebih aman gagal-cepat sebelum ada baris yang tertinggal. Kalau Kepala Ruangannya
        // justru rekan atau si pemohon sendiri, step-nya dilewati (tidak approve dua kali / approve
        // giliran sendiri) - itu tetap valid.
        $headUserId = $swapRequest->reqSchedule?->shiftType?->schedule?->head_user_id;

        if (! $headUserId) {
            throw new \RuntimeException(
                'Kepala Ruangan untuk jadwal shift ini belum ditentukan, jadi pengajuan tukar shift '
                . 'belum bisa diproses. Minta admin mengatur Kepala Ruangan jadwal terlebih dahulu.'
            );
        }

        $prependSteps = [[
            'approver_type' => 'user',
            'approver_ref_id' => $swapRequest->target_id,
        ]];

        if (! in_array($headUserId, [$user->id, $swapRequest->target_id], true)) {
            $prependSteps[] = ['approver_type' => 'user', 'approver_ref_id' => $headUserId];
        }

        $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
            moduleId:   $swapRequest->id,
            flow:       $masterFlow,
            createdBy:  $user->id,
            companyId:  $user->company_id,
            userId:     $user->id,
            prependSteps: $prependSteps,
        );
    }

    /** Rekan (target_id) kasih tanggapan opsional - murni catatan info, TIDAK menyentuh `status`
     * atau approval snapshot sama sekali (lihat komentar ASK_TARGET_RESPONSE_SETTING_KEY). */
    public function recordTargetResponse(ShiftSwapRequests $request, User $actor, string $response): void
    {
        abort_unless($actor->id === $request->target_id, 403);

        if (! in_array($response, ['accepted', 'declined'], true)) {
            throw new \InvalidArgumentException('Tanggapan tidak valid.');
        }

        $request->update([
            'target_response' => $response,
            'target_responded_at' => now(),
        ]);
    }

    /** Rekan sekarang approver step 1 sungguhan, jadi begitu dia approve/reject STEP-nya, kolom
     * `target_response` (yang tampil sebagai "Tanggapan Rekan" di halaman detail) ikut diisi biar
     * tidak nyangkut "Belum menanggapi" padahal timeline-nya sudah lewat rekan. Dipanggil dari
     * ShiftSwapApprovalsCard & ShiftSwapRequestDetail sesudah advanceStep berhasil.
     *
     * @param int $stepOrderBefore current_step_order SEBELUM advanceStep (step rekan selalu = 1).
     */
    public function syncTargetResponseFromApprovalStep(
        ShiftSwapRequests $request,
        string $actorId,
        int $stepOrderBefore,
        string $action,
    ): void {
        if ($actorId !== $request->target_id || $stepOrderBefore !== 1) {
            return;
        }

        $response = match ($action) {
            'approved' => 'accepted',
            'rejected' => 'declined',
            default => null,
        };

        if ($response === null || $request->target_response !== null) {
            return;
        }

        $request->update([
            'target_response' => $response,
            'target_responded_at' => now(),
        ]);
    }
}
