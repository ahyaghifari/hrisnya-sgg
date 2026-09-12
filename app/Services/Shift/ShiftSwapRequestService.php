<?php

namespace App\Services\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Attendance\AttendanceSettingEnum;
use App\Models\Setting;
use App\Services\Attendance\AttendancePeriodResolver;
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

        $this->assertWithinSettingLimits($data, $reqSchedule, $user);

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
    }

    /** Jarak minimal hari (AttendanceSettingEnum::TUKAR_SHIFT_MIN_LEAD_DAYS) antara hari ini dan
     * tanggal shift saat mengajukan tukar shift. 0 = tidak dibatasi. */
    public function minLeadDays(?string $companyId): int
    {
        return max(0, (int) Setting::resolve(AttendanceSettingEnum::TUKAR_SHIFT_MIN_LEAD_DAYS->value, $companyId, 0));
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

    /** Batas dari Pengaturan Absensi (company-scoped) - lihat minLeadDays() & swapQuota(). */
    private function assertWithinSettingLimits(array $data, ScheduleShiftAssignment $reqSchedule, $user): void
    {
        $minLead = $this->minLeadDays($user->company_id);
        if ($minLead > 0) {
            $earliest = $reqSchedule->date->copy();
            if (! empty($data['target_schedule_id'])) {
                $targetDate = ScheduleShiftAssignment::whereKey($data['target_schedule_id'])->value('date');
                if ($targetDate && $targetDate->lt($earliest)) {
                    $earliest = $targetDate;
                }
            }

            if ($earliest->lt(today()->addDays($minLead))) {
                throw new \InvalidArgumentException(
                    "Pengajuan tukar shift harus dibuat minimal {$minLead} hari sebelum tanggal shift."
                );
            }
        }

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
     * bukan konflik beneran) ditimpa, slot yang "conflict" nyata ikut `swap_kind`:
     * 'schedule' (default) DIBIARKAN APA ADANYA (warning-only, lihat conflictWarnings());
     * 'reciprocal_off' (tukar libur berpasangan) malah harus GAGAL total (exception naik ke
     * ApprovalFlowService::advanceStep, approval tidak lanjut) - supaya tidak ada sisi yang
     * partial-execute (pemohon untung, rekan tidak jadi dapat libur balik). Lihat
     * planning/2026-09-12-tukar-shift-libur-berpasangan.md. */
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

        $plan = $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule, strict: $swapRequest->swap_kind === 'reciprocal_off');

        $reqUserId = $reqSchedule->user_id;
        $targetUserId = $targetSchedule->user_id;

        $this->applySlotResolution($plan['req_date'], $reqSchedule, $targetUserId);
        $this->applySlotResolution($plan['target_date'], $targetSchedule, $reqUserId);
    }

    /** @param array{mode: 'move'|'move_over_off_day'|'conflict', busy?: ScheduleShiftAssignment, warning?: string} $slot */
    private function applySlotResolution(array $slot, ScheduleShiftAssignment $originalSchedule, string $incomingUserId): void
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
        }

        // mode 'conflict' -> sengaja no-op (kecuali strict, sudah throw duluan di resolveSlot()).
        // $originalSchedule tetap milik pemiliknya, tidak dipindah - lihat docblock executeApprovedSwap().
    }

    /** Tentukan siapa yang akhirnya nempatin baris req_schedule (tanggal pemohon) dan baris
     * target_schedule (tanggal rekan) buat kasus tukar BEDA tanggal. Kalau salah satu pihak
     * TERNYATA sudah punya baris lain di tanggal yang dituju: baris Libur (off_day=true) boleh
     * ditimpa (mode 'move_over_off_day', bukan konflik beneran); baris shift KERJA beneran jadi
     * mode 'conflict' - dengan `$strict=false` (default) cuma warning & TIDAK diblokir/gagal
     * (instruksi user 2026-09-12); dengan `$strict=true` (dipakai swap_kind='reciprocal_off')
     * malah throw supaya submit/approval gagal total, lihat resolveSlot(). Read-only, aman
     * dipanggil berkali-kali: sekali pas eksekusi approval final (executeApprovedSwap, hasilnya
     * dipakai), berkali-kali lagi buat validasi pra-submit reciprocal_off & preview warning
     * (conflictWarnings/conflictWarningsForSchedules) di form pengajuan & halaman detail -
     * supaya perubahan jadwal di antara momen-momen itu tetap ke-cover ulang.
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
            'req_date' => $this->resolveSlot($targetConflict, $targetSchedule->user, 'Karyawan tujuan sudah memiliki jadwal lain pada tanggal shift Anda', $strict),
            'target_date' => $this->resolveSlot($reqConflict, $reqSchedule->user, 'Anda sudah memiliki jadwal lain pada tanggal shift tujuan', $strict),
        ];
    }

    /** @return array{mode: 'move'|'move_over_off_day'|'conflict', busy?: ScheduleShiftAssignment, warning?: string} */
    private function resolveSlot(?ScheduleShiftAssignment $busy, ?User $incomingOwner, string $conflictMessagePrefix, bool $strict = false): array
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

        if ($strict) {
            throw new \InvalidArgumentException(
                "{$conflictMessagePrefix} ({$busyShift}) - tukar libur berpasangan butuh tanggal ini benar-benar Libur/kosong di kedua belah pihak."
            );
        }

        $name = $incomingOwner?->name ?? 'Karyawan';

        return [
            'mode' => 'conflict',
            'busy' => $busy,
            'warning' => "{$conflictMessagePrefix} ({$busyShift}) - shift ini TIDAK dipindahkan otomatis supaya {$name} tidak double shift/nyambung shift. Kalau tetap perlu dikerjakan, tambahkan Extra Shift manual lewat halaman Extra Shift.",
        ];
    }

    /** Peringatan double shift/nyambung shift buat pengajuan yang BELUM disimpan (preview di
     * form pengajuan) - read-only, tidak menyentuh DB. [] kalau tidak ada konflik atau tukar
     * tanggal sama (kompatibilitas shift_type dicek terpisah di validateScheduleSwapRules). */
    public function conflictWarningsForSchedules(?ScheduleShiftAssignment $reqSchedule, ?ScheduleShiftAssignment $targetSchedule): array
    {
        if (! $reqSchedule || ! $targetSchedule || $reqSchedule->date->equalTo($targetSchedule->date)) {
            return [];
        }

        $plan = $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule);

        return array_values(array_filter([
            $plan['req_date']['warning'] ?? null,
            $plan['target_date']['warning'] ?? null,
        ]));
    }

    /** Sama seperti conflictWarningsForSchedules(), tapi untuk pengajuan yang SUDAH tersimpan
     * (dipakai halaman detail) - dihitung ulang live tiap dipanggil, bukan snapshot saat submit,
     * supaya tetap akurat kalau jadwal berubah setelah pengajuan dibuat. */
    public function conflictWarnings(ShiftSwapRequests $request): array
    {
        $request->loadMissing(['reqSchedule.shiftType', 'targetSchedule.shiftType']);

        return $this->conflictWarningsForSchedules($request->reqSchedule, $request->targetSchedule);
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
