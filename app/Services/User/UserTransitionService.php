<?php

namespace App\Services\User;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Models\Master\JobPositions;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserTransition;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logic Perubahan Status Karyawan (Promosi/Demosi/Mutasi/PHK) — dipakai BERSAMA oleh admin
 * Filament (CreateUserTransition/EditUserTransition) dan portal (Kepegawaian). Diekstrak dari
 * 2 Page class admin supaya tidak duplikat antara admin & portal — approve/reject harus
 * berperilaku identik di kedua tempat (termasuk efek sampingnya: ganti jabatan/role/nonaktifkan
 * user), bukan 2 implementasi terpisah yang bisa mencar seiring waktu.
 */
class UserTransitionService
{
    public function __construct(
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalFlowService $approvalFlowService,
    ) {}

    /**
     * @param array<string, mixed> $data field header (user_id, transition_type, transitionable_id,
     *   role_id, effective_date, reason, manager_comment, dst — sama seperti kolom UserTransition)
     * @param array<string, mixed> $detailData field evaluasi kinerja (UserTransitionDetail), cuma
     *   relevan untuk Promosi/Demosi
     * @param array<int, string> $attachmentPaths path file yang sudah diupload
     */
    public function create(array $data, array $detailData, array $attachmentPaths, User $actingUser): UserTransition
    {
        $targetUser = User::findOrFail($data['user_id']);

        $data['number'] = UserTransition::generateNumber($data['transition_type'], $actingUser->company);
        $data['company_id'] = $actingUser->company_id;
        $data['status'] = UserTransitionStatusEnum::PENDING->value;
        $data['created_by'] = $actingUser->id;

        // old_value = kondisi TARGET karyawan sebelum perubahan (buat riwayat/audit), bukan
        // kondisi pembuat pengajuan. Promosi/Demosi/Mutasi Internal semua pindah jabatan
        // (departemen/divisi ikut jabatan barunya) — bedanya cuma Promosi/Demosi punya form
        // evaluasi kinerja. PHK tidak pilih jabatan (transitionable_id null).
        if (in_array($data['transition_type'], [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value,
            UserTransitionTypeEnum::MUTATION->value,
        ], true)) {
            $data['transitionable_type'] = JobPositions::class;
            $data['old_value'] = $targetUser->profile?->jobPosition?->name;
        }

        $transition = UserTransition::create($data);

        if (! empty($detailData)) {
            $transition->detail()->create($detailData);
        }

        foreach ($attachmentPaths as $path) {
            $transition->attachments()->create(['file_path' => $path]);
        }

        $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
            companyId: $actingUser->company_id,
        );

        if (! $masterFlow) {
            throw new \Exception('Tidak ada approval flow aktif untuk perubahan status karyawan pada perusahaan ini.');
        }

        $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
            moduleId: $transition->id,
            flow: $masterFlow,
            createdBy: $actingUser->id,
            companyId: $actingUser->company_id,
            userId: $actingUser->id,
        );

        return $transition;
    }

    /**
     * Hitung tanggal berakhir kontrak otomatis (anchor + durasi perpanjangan dari
     * KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN, atau $monthsOverride kalau diisi —
     * dipakai modul Perpanjangan Kontrak di mana penilai boleh set durasi sendiri), sekaligus
     * validasi 2 batas company-scoped: total masa kerja Kontrak dari Tanggal Bergabung
     * (MAX_KONTRAK_TAHUN) dan usia pensiun dari Tanggal Lahir (USIA_PENSIUN_TAHUN) - throw kalau
     * melanggar, gagal-cepat sebelum record dibuat. 0 di setting = tidak dibatasi.
     */
    public function resolveContractEndDate(User $user, Carbon $anchor, ?string $companyId, ?int $monthsOverride = null): Carbon
    {
        $months = $monthsOverride ?? (int) Setting::resolve(
            KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->value,
            $companyId,
            default: (int) KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->default()
        );
        $contractEndDate = $anchor->copy()->addMonths(max(1, $months));

        $maxTahun = (int) Setting::resolve(
            KepegawaianSettingEnum::MAX_KONTRAK_TAHUN->value,
            $companyId,
            default: (int) KepegawaianSettingEnum::MAX_KONTRAK_TAHUN->default()
        );
        if ($maxTahun > 0 && $user->profile?->joining_date) {
            $maxEndDate = Carbon::parse($user->profile->joining_date)->addYears($maxTahun);
            if ($contractEndDate->greaterThan($maxEndDate)) {
                throw new \Exception("Melebihi batas maksimal {$maxTahun} tahun sebagai karyawan Kontrak (dihitung dari Tanggal Bergabung), maksimal sampai {$maxEndDate->translatedFormat('d F Y')}.");
            }
        }

        $usiaPensiun = (int) Setting::resolve(
            KepegawaianSettingEnum::USIA_PENSIUN_TAHUN->value,
            $companyId,
            default: (int) KepegawaianSettingEnum::USIA_PENSIUN_TAHUN->default()
        );
        if ($usiaPensiun > 0 && $user->profile?->date_of_birth) {
            $ageAtEnd = Carbon::parse($user->profile->date_of_birth)->diffInYears($contractEndDate);
            if ($ageAtEnd >= $usiaPensiun) {
                throw new \Exception("Karyawan akan mencapai usia pensiun ({$usiaPensiun} tahun) sebelum {$contractEndDate->translatedFormat('d F Y')}.");
            }
        }

        return $contractEndDate;
    }

    /** Buat approval snapshot kalau belum ada (jaga-jaga, sama seperti EditUserTransition::mount()
     * lama) — dan sinkronkan step kalau master flow berubah sementara masih pending. */
    public function ensureApprovalSnapshot(UserTransition $transition): void
    {
        if (! $transition->approvalSnapshot) {
            $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
                companyId: $transition->company_id,
                userId: $transition->created_by,
            );

            if ($masterFlow) {
                $this->approvalCreationService->createSnapshot(
                    moduleType: ApprovalModuleEnum::USER_TRANSITION->value,
                    moduleId: $transition->id,
                    flow: $masterFlow,
                    createdBy: $transition->created_by,
                    companyId: $transition->company_id,
                    userId: $transition->created_by,
                );
                $transition->load('approvalSnapshot.activity');
            }

            return;
        }

        $inFinalState = in_array($transition->status, [
            UserTransitionStatusEnum::APPROVED->value,
            UserTransitionStatusEnum::REJECTED->value,
        ], true);

        if (! $inFinalState && ! $this->approvalFlowService->checkStepsSync($transition->approvalSnapshot)) {
            $this->approvalFlowService->syncStepsFromMaster($transition->approvalSnapshot);
            $transition->update(['is_flow_changed' => true]);
        }
    }

    public function approve(UserTransition $transition, string $actorId, ?string $note = null): void
    {
        $this->approvalFlowService->advanceStep($transition->approvalSnapshot, $actorId, $note, 'approved');

        $transition->update([
            'status' => UserTransitionStatusEnum::APPROVED->value,
            'approved_at' => now(),
            'approved_by' => $actorId,
        ]);

        // Tanggal efektif masa depan -> efek ke data karyawan DITUNDA; command `transitions:apply-due`
        // yang menerapkannya pas tanggalnya tiba. Tanggal efektif hari ini / lampau / kosong ->
        // langsung diterapkan sekarang (perilaku lama). applied_at = penanda sudah diterapkan
        // (sekaligus guard biar command tidak menerapkan ulang).
        if ($this->effectiveDateReached($transition)) {
            DB::transaction(function () use ($transition) {
                $this->applyTransitionChanges($transition);
                $transition->update(['applied_at' => now()]);
            });
        }

        $transition->refresh();
    }

    private function effectiveDateReached(UserTransition $transition): bool
    {
        if (! $transition->effective_date) {
            return true;
        }

        return Carbon::parse($transition->effective_date)->startOfDay()->lte(Carbon::today());
    }

    /**
     * Terapkan semua transisi yang sudah disetujui & tanggal efektifnya sudah tiba tapi belum
     * pernah diterapkan (applied_at NULL). Dipanggil harian oleh command `transitions:apply-due`.
     * Per-baris di-guard: satu transisi gagal (mis. jabatan tujuan keburu dihapus, user keburu
     * dihapus) tidak memblok sisanya.
     *
     * @return array{applied: int, failed: int}
     */
    public function applyDueTransitions(): array
    {
        $due = UserTransition::query()
            ->where('status', UserTransitionStatusEnum::APPROVED->value)
            ->whereNull('applied_at')
            ->whereNotNull('effective_date')
            ->whereDate('effective_date', '<=', Carbon::today())
            ->get();

        $applied = 0;
        $failed = 0;

        foreach ($due as $transition) {
            try {
                DB::transaction(function () use ($transition) {
                    $this->applyTransitionChanges($transition);
                    $transition->update(['applied_at' => now()]);
                });
                $applied++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Gagal menerapkan transisi terjadwal (transitions:apply-due)', [
                    'user_transition_id' => $transition->id,
                    'transition_type' => $transition->transition_type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['applied' => $applied, 'failed' => $failed];
    }

    public function reject(UserTransition $transition, string $actorId): void
    {
        $this->approvalFlowService->advanceStep($transition->approvalSnapshot, $actorId, null, 'rejected');

        $transition->update(['status' => UserTransitionStatusEnum::REJECTED->value]);
    }

    /** Efek nyata ke data karyawan setelah transisi di-approve — jabatan/role baru
     * (Promosi/Demosi/Mutasi Internal) atau nonaktifkan user (PHK). */
    private function applyTransitionChanges(UserTransition $transition): void
    {
        $user = $transition->user;
        $type = $transition->transition_type;

        if (in_array($type, [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value,
            UserTransitionTypeEnum::MUTATION->value,
        ], true)) {
            $currentPosition = $user->jobPositions()->whereNull('end_date')->first();

            if ($currentPosition) {
                $currentPosition->update(['end_date' => now(), 'status' => 'inactive']);
            }

            $user->jobPositions()->create([
                'job_position_id' => $transition->transitionable_id,
                'assigned_by' => $transition->approved_by,
                'start_date' => $transition->effective_date,
                'status' => 'active',
            ]);

            $jobPosition = JobPositions::find($transition->transitionable_id);

            $user->profile->update([
                'job_position_id' => $jobPosition->id,
                'pangkat_id' => $jobPosition->pangkat_id,
                'kelas_id' => $jobPosition->kelas_id,
            ]);

            if ($transition->role_id) {
                $user->syncRoles([$transition->role]);
            }

            return;
        }

        if ($type === UserTransitionTypeEnum::TERMINATION->value) {
            // Jabatan aktif ditutup di Tanggal Efektif PHK (bukan now()) - sejajar dengan
            // Promosi/Demosi/Mutasi yang men-date baris ledger jabatan dari effective_date.
            $endDate = $transition->effective_date ?? now();

            $user->update(['is_active' => false]);

            $user->profile->update([
                'job_position_id' => null,
                'pangkat_id' => null,
                'kelas_id' => null,
                'org_structure_id' => null,
                'employee_type_id' => null,
            ]);

            $user->jobPositions()->whereNull('end_date')->update([
                'end_date' => $endDate,
                'status' => 'inactive',
            ]);

            // Arsipkan akun: soft delete (deleted_at) sehingga tidak muncul di query User biasa
            // & tidak bisa login. Riwayat (payroll/absensi/audit) tetap ada, dan masih bisa
            // dipulihkan lewat restore() kalau PHK dibatalkan. is_active=false tetap diset di
            // atas buat tampilan yang sengaja withTrashed().
            $user->delete();
        }
    }
}
