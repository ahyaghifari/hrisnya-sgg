<?php

namespace App\Services\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Setting;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Notifications\ShiftSwapTargetResponseRequested;
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

        // Ensure neither user has another schedule on the date they would move to. Kedua baris
        // yang terlibat swap ($reqSchedule & $targetSchedule) DIKECUALIKAN dari kedua cek -
        // penting buat kasus tukar tanggal SAMA (mis. pagi<->siang hari yang sama): baris milik
        // targetSchedule sendiri akan selalu "match" query pertama (sama-sama di reqSchedule->date
        // saat tanggalnya sama) kalau cuma reqSchedule->id yang dikecualikan, salah dianggap
        // konflik padahal itu baris yang memang jadi bagian swap ini.
        if (ScheduleShiftAssignment::where('user_id', $targetSchedule->user_id)
            ->whereDate('date', $reqSchedule->date)
            ->whereNotIn('id', [$reqSchedule->id, $targetSchedule->id])
            ->exists()) {
            throw new \InvalidArgumentException(
                'Karyawan tujuan sudah memiliki jadwal lain pada tanggal shift Anda, tidak dapat ditukar.'
            );
        }

        if (ScheduleShiftAssignment::where('user_id', $reqSchedule->user_id)
            ->whereDate('date', $targetSchedule->date)
            ->whereNotIn('id', [$reqSchedule->id, $targetSchedule->id])
            ->exists()) {
            throw new \InvalidArgumentException(
                'Anda sudah memiliki jadwal lain pada tanggal shift tujuan, tidak dapat ditukar.'
            );
        }

        // shift_type compatibility: pagi ↔ siang diperbolehkan, malam hanya dengan malam
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

        $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
            moduleId:   $swapRequest->id,
            flow:       $masterFlow,
            createdBy:  $user->id,
            companyId:  $user->company_id,
            userId:     $user->id,
        );

        if (Setting::resolve(self::ASK_TARGET_RESPONSE_SETTING_KEY, $user->company_id, default: false)) {
            $swapRequest->target?->notify(new ShiftSwapTargetResponseRequested($swapRequest));
        }
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
}
