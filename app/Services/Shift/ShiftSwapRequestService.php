<?php

namespace App\Services\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Attendance\AttendanceSettingEnum;
use App\Models\Setting;
use App\Models\Shift\ExtraShiftAssignment;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Models\Shift\ExtraShiftRule;
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
        $user    = Auth::user();
        $mode    = $data['swap_mode'] ?? 'schedule';

        $reqSchedule = ScheduleShiftAssignment::with('shiftType')->findOrFail($data['req_schedule_id']);

        $this->assertWithinSettingLimits($data, $reqSchedule, $user);

        if ($mode === 'off_day') {
            return $this->storeOffDay($data, $reqSchedule, $user);
        }

        return $this->storeScheduleSwap($data, $reqSchedule, $user);
    }

    private function storeScheduleSwap(
        array $data,
        ScheduleShiftAssignment $reqSchedule,
        $user,
    ): ShiftSwapRequests {
        $targetSchedule = ScheduleShiftAssignment::with('shiftType')->findOrFail($data['target_schedule_id']);

        $this->validateScheduleSwapRules($reqSchedule, $targetSchedule);
        $this->assertNoPendingSwapOnSlots([
            [$reqSchedule->user_id, $reqSchedule->date],
            [$targetSchedule->user_id, $targetSchedule->date],
        ]);

        return DB::transaction(function () use ($data, $reqSchedule, $targetSchedule, $user) {
            $swapRequest = ShiftSwapRequests::create([
                'company_id'         => $user->company_id,
                'requester_id'       => $user->id,
                'target_id'          => $targetSchedule->user_id,
                'req_schedule_id'    => $reqSchedule->id,
                'target_schedule_id' => $targetSchedule->id,
                'req_date'           => $reqSchedule->date,
                'target_date'        => $targetSchedule->date,
                'status'             => 'in_review',
                'notes'              => $data['notes'] ?? null,
            ]);

            $this->createApprovalSnapshot($swapRequest, $user);

            return $swapRequest;
        });
    }

    private function storeOffDay(
        array $data,
        ScheduleShiftAssignment $reqSchedule,
        $user,
    ): ShiftSwapRequests {
        $targetUserId = $data['target_user_off']
            ?? throw new \InvalidArgumentException('Pilih karyawan penerima shift.');

        $targetUser = User::findOrFail($targetUserId);

        $this->validateOffDayRules($reqSchedule, $targetUserId);
        $this->assertNoPendingSwapOnSlots([
            [$reqSchedule->user_id, $reqSchedule->date],
            [$targetUserId, $reqSchedule->date],
        ]);

        return DB::transaction(function () use ($reqSchedule, $targetUser, $user, $data) {
            $swapRequest = ShiftSwapRequests::create([
                'company_id'         => $user->company_id,
                'requester_id'       => $user->id,
                'target_id'          => $targetUser->id,
                'req_schedule_id'    => $reqSchedule->id,
                'target_schedule_id' => null, // target is off – no existing assignment
                'req_date'           => $reqSchedule->date,
                'target_date'        => $reqSchedule->date, // same date – target receives on this day
                'status'             => 'in_review',
                'notes'              => $data['notes'] ?? null,
            ]);

            $this->createApprovalSnapshot($swapRequest, $user);

            return $swapRequest;
        });
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

        // Kalau salah satu (atau kedua) pihak TERNYATA sudah punya shift lain (bukan libur) di
        // tanggal tujuan, sekarang tidak langsung diblokir - dicoba dulu jalur Extra Shift
        // (throw sendiri kalau memang tidak ada rule yang cocok). Hasilnya dibuang di sini, cuma
        // dipakai buat validasi gagal-cepat saat pengajuan - eksekusi beneran (buat baris DB)
        // baru jalan pas approval final lewat executeApprovedSwap(), lihat method itu.
        $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule);

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

    private function validateOffDayRules(
        ScheduleShiftAssignment $reqSchedule,
        string $targetUserId,
    ): void {
        // Target must truly have no schedule on req_date
        $conflict = ScheduleShiftAssignment::where('user_id', $targetUserId)
            ->whereDate('date', $reqSchedule->date)
            ->exists();

        if ($conflict) {
            throw new \InvalidArgumentException(
                'Karyawan tujuan sudah memiliki jadwal pada tanggal tersebut.'
            );
        }
    }

    /** Dipanggil dari ApprovalFlowService pas swap request disetujui final - satu-satunya tempat
     * yang benar-benar menulis perubahan ke schedule_shift_assignments/extra_shift_assignments
     * (bukan pas diajukan). Tanggal sama -> tukar shift_type_id (hindari bentrok unique
     * (user_id,date)). Tanggal beda -> re-cek konflik (bisa berubah sejak pengajuan) lalu jalankan
     * hasilnya: slot yang "move" pindah user_id seperti biasa, slot yang "extra_shift" baris
     * aslinya dihapus (pemiliknya dibebaskan) dan pihak yang sudah sibuk di tanggal itu dapat
     * ExtraShiftAssignment tambahan buat nutupnya. */
    public function executeApprovedSwap(ShiftSwapRequests $swapRequest): void
    {
        $swapRequest->loadMissing(['reqSchedule.shiftType', 'targetSchedule.shiftType', 'requester', 'target']);

        $reqSchedule = $swapRequest->reqSchedule;
        $targetSchedule = $swapRequest->targetSchedule;

        if ($reqSchedule && $targetSchedule) {
            if ($reqSchedule->date->equalTo($targetSchedule->date)) {
                $reqShiftTypeId = $reqSchedule->shift_type_id;
                $targetShiftTypeId = $targetSchedule->shift_type_id;
                $reqSchedule->update(['shift_type_id' => $targetShiftTypeId]);
                $targetSchedule->update(['shift_type_id' => $reqShiftTypeId]);

                return;
            }

            $plan = $this->resolveDifferentDateExecution($reqSchedule, $targetSchedule);

            $reqUserId = $reqSchedule->user_id;
            $targetUserId = $targetSchedule->user_id;

            $this->applySlotResolution($plan['req_date'], $reqSchedule, $targetUserId, $swapRequest, $swapRequest->requester?->name);
            $this->applySlotResolution($plan['target_date'], $targetSchedule, $reqUserId, $swapRequest, $swapRequest->target?->name);

            return;
        }

        if ($reqSchedule && ! $targetSchedule) {
            // Re-cek konflik sebelum eksekusi - alasan sama seperti cabang beda-tanggal di atas
            // (bisa berubah sejak pengajuan, target bisa saja kebagian jadwal baru di tanggal ini
            // di antara submit & approval final). Tanpa ini, update() bisa nabrak unique
            // constraint (user_id, date) dan lempar QueryException mentah di tengah transaction.
            $this->validateOffDayRules($reqSchedule, $swapRequest->target_id);

            $reqSchedule->update(['user_id' => $swapRequest->target_id]);
        }
    }

    /** @param array{mode: 'move'|'extra_shift', busy?: ScheduleShiftAssignment} $slot */
    private function applySlotResolution(
        array $slot,
        ScheduleShiftAssignment $originalSchedule,
        string $incomingUserId,
        ShiftSwapRequests $swapRequest,
        ?string $originalOwnerName,
    ): void {
        if ($slot['mode'] === 'move') {
            $originalSchedule->update(['user_id' => $incomingUserId]);

            return;
        }

        $shiftName = $originalSchedule->shiftType?->name ?? 'shift';
        $date = $originalSchedule->date->translatedFormat('d M Y');

        ExtraShiftAssignment::create([
            'company_id' => $originalSchedule->company_id,
            'user_id' => $slot['busy']->user_id,
            'shift_type_id' => $originalSchedule->shift_type_id,
            'date' => $originalSchedule->date,
            'status' => 'approved',
            'source' => 'shift_swap',
            'reason' => "Otomatis dari pengajuan tukar shift yang sudah disetujui - menggantikan {$shiftName} milik {$originalOwnerName} pada {$date}.",
            'created_by' => $swapRequest->approved_by,
        ]);

        $originalSchedule->delete();
    }

    /** Tentukan siapa yang akhirnya nempatin baris req_schedule (tanggal pemohon) dan baris
     * target_schedule (tanggal rekan) buat kasus tukar BEDA tanggal. Kalau salah satu pihak
     * TERNYATA sudah punya shift lain (bukan libur) di tanggal yang dituju, pihak itu tidak bisa
     * "pindah" langsung (unique (user_id,date)) - fallback ke Extra Shift kalau ExtraShiftRule
     * mengizinkan kombinasinya, atau throw kalau tidak ada rule yang cocok. Dipanggil 2x: sekali
     * pas validasi pengajuan (hasil dibuang, cuma buat gagal-cepat), sekali lagi pas eksekusi
     * approval final (hasilnya dipakai) - supaya perubahan jadwal di antara 2 momen itu tetap
     * ke-cover ulang.
     *
     * @return array{req_date: array{mode:string,busy?:ScheduleShiftAssignment}, target_date: array{mode:string,busy?:ScheduleShiftAssignment}}
     */
    private function resolveDifferentDateExecution(
        ScheduleShiftAssignment $reqSchedule,
        ScheduleShiftAssignment $targetSchedule,
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
            'req_date' => $this->resolveSlot($targetConflict, $reqSchedule, 'Karyawan tujuan sudah memiliki jadwal lain pada tanggal shift Anda'),
            'target_date' => $this->resolveSlot($reqConflict, $targetSchedule, 'Anda sudah memiliki jadwal lain pada tanggal shift tujuan'),
        ];
    }

    /** @return array{mode: 'move'|'extra_shift', busy?: ScheduleShiftAssignment} */
    private function resolveSlot(?ScheduleShiftAssignment $busy, ScheduleShiftAssignment $incoming, string $blockMessagePrefix): array
    {
        if (! $busy) {
            return ['mode' => 'move'];
        }

        // Sudah punya extra shift LAIN di tanggal itu (dari sumber mana pun) - tidak boleh
        // ditumpuk dobel, sama seperti aturan di ResolvesExtraShiftOptions::resolveAllowedExtraShiftTypes()
        // yang dipakai jalur Extra Shift manual.
        if (ExtraShiftAssignment::where('user_id', $busy->user_id)->whereDate('date', $incoming->date)->exists()) {
            throw new \InvalidArgumentException(
                "{$blockMessagePrefix}, dan tidak bisa ditutup Extra Shift karena karyawan itu sudah punya extra shift lain di tanggal itu."
            );
        }

        $ruleExists = ExtraShiftRule::where('company_id', $incoming->company_id)
            ->where('from_shift_type', $busy->shiftType?->shift_type)
            ->where('to_shift_type', $incoming->shiftType?->shift_type)
            ->exists();

        if (! $ruleExists) {
            throw new \InvalidArgumentException(
                "{$blockMessagePrefix}, dan tidak ada aturan Extra Shift dari \"{$busy->shiftType?->name}\" ke \"{$incoming->shiftType?->name}\" untuk menutupinya."
            );
        }

        return ['mode' => 'extra_shift', 'busy' => $busy];
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
