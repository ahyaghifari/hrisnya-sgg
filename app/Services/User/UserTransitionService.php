<?php

namespace App\Services\User;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\EmployeeTypeCategory;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\UserTransitionStatusEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserContract;
use App\Models\User\UserTransition;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Carbon\Carbon;

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

        // old_value = kondisi TARGET karyawan sebelum perubahan (buat riwayat/audit) — bukan
        // kondisi user yang membuat pengajuan.
        if (in_array($data['transition_type'], [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value,
        ], true)) {
            $data['transitionable_type'] = JobPositions::class;
            $data['old_value'] = $targetUser->profile?->jobPosition?->name;
        }

        if ($data['transition_type'] === UserTransitionTypeEnum::MUTATION->value) {
            $data['transitionable_type'] = OrgStructure::class;
            $data['old_value'] = $targetUser->profile?->orgStructure?->name;
        }

        if ($data['transition_type'] === UserTransitionTypeEnum::KELAS_CHANGE->value) {
            $data['transitionable_type'] = Kelas::class;
            $data['old_value'] = $targetUser->profile?->kelas?->name;
        }

        if ($data['transition_type'] === UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value) {
            $data['transitionable_type'] = EmployeeTypes::class;
            $data['old_value'] = $targetUser->profile?->employeeType?->name;

            // contract_start_date/contract_end_date dihitung otomatis (bukan input manual) - cuma
            // relevan kalau status barunya Kontrak. Start = Tanggal Efektif itu sendiri (anchor
            // yang sama dipakai buat hitung end date).
            $newEmployeeType = EmployeeTypes::find($data['transitionable_id']);
            $effectiveDate = Carbon::parse($data['effective_date']);
            $data['contract_start_date'] = $newEmployeeType?->category === EmployeeTypeCategory::KONTRAK
                ? $effectiveDate->toDateString()
                : null;
            $data['contract_end_date'] = $newEmployeeType?->category === EmployeeTypeCategory::KONTRAK
                ? $this->resolveContractEndDate($targetUser, $effectiveDate, $actingUser->company_id)->toDateString()
                : null;
        }

        if ($data['transition_type'] === UserTransitionTypeEnum::CONTRACT_EXTENSION->value) {
            $currentContractEndDate = $targetUser->currentContract?->contract_end_date;
            $data['old_value'] = $currentContractEndDate;

            // Anchor dari tanggal kontrak SEKARANG (atau hari ini kalau sudah lewat/belum ada),
            // bukan dari effective_date - perpanjangan menyambung dari kontrak yang berjalan.
            // Anchor itu sendiri jadi contract_start_date (mulai term kontrak baru).
            $anchor = $currentContractEndDate
                ? Carbon::parse($currentContractEndDate)->max(Carbon::today())
                : Carbon::today();
            $data['contract_start_date'] = $anchor->toDateString();
            $data['contract_end_date'] = $this->resolveContractEndDate($targetUser, $anchor, $actingUser->company_id)->toDateString();
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
     * KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN), sekaligus validasi 2 batas company-
     * scoped: total masa kerja Kontrak dari Tanggal Bergabung (MAX_KONTRAK_TAHUN) dan usia
     * pensiun dari Tanggal Lahir (USIA_PENSIUN_TAHUN) - throw kalau melanggar, gagal-cepat
     * sebelum record dibuat. 0 di setting = tidak dibatasi.
     */
    private function resolveContractEndDate(User $user, Carbon $anchor, ?string $companyId): Carbon
    {
        $months = (int) Setting::resolve(
            KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->value,
            $companyId,
            default: (int) KepegawaianSettingEnum::PERPANJANGAN_KONTRAK_BULAN->default()
        );
        $contractEndDate = $anchor->copy()->addMonths($months);

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

    public function approve(UserTransition $transition, string $actorId): void
    {
        $this->approvalFlowService->advanceStep($transition->approvalSnapshot, $actorId, null, 'approved');

        $transition->update([
            'status' => UserTransitionStatusEnum::APPROVED->value,
            'approved_at' => now(),
            'approved_by' => $actorId,
        ]);

        $this->applyTransitionChanges($transition);

        $transition->refresh();
    }

    public function reject(UserTransition $transition, string $actorId): void
    {
        $this->approvalFlowService->advanceStep($transition->approvalSnapshot, $actorId, null, 'rejected');

        $transition->update(['status' => UserTransitionStatusEnum::REJECTED->value]);
    }

    /** Efek nyata ke data karyawan setelah transisi di-approve — jabatan/role baru (Promosi/
     * Demosi), departemen baru (Mutasi), atau nonaktifkan user (PHK). */
    private function applyTransitionChanges(UserTransition $transition): void
    {
        $user = $transition->user;
        $type = $transition->transition_type;

        if (in_array($type, [
            UserTransitionTypeEnum::PROMOTION->value,
            UserTransitionTypeEnum::DEMOTION->value,
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

        if ($type === UserTransitionTypeEnum::MUTATION->value) {
            $user->profile?->update(['org_structure_id' => $transition->transitionable_id]);

            return;
        }

        if ($type === UserTransitionTypeEnum::KELAS_CHANGE->value) {
            $user->profile?->update(['kelas_id' => $transition->transitionable_id]);

            return;
        }

        if ($type === UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value) {
            $newEmployeeType = EmployeeTypes::find($transition->transitionable_id);

            $updates = ['employee_type_id' => $transition->transitionable_id];

            // Kelas otomatis ikut minimal kelas dari Pangkat yang sudah di-assign ke karyawan
            // (Pangkats::startKelas()) - bukan dipilih manual, dikonfirmasi user: pangkat sudah
            // menentukan kelas minimalnya.
            $startKelasId = $user->profile?->pangkat?->start_kelas_id;
            if ($startKelasId) {
                $updates['kelas_id'] = $startKelasId;
            }

            $user->profile?->update($updates);

            // Ledger riwayat kontrak (UserContract) cuma dicatat kalau status barunya Kontrak -
            // "kontrak aktif saat ini" dibaca lewat User::currentContract(), bukan kolom di profile.
            // Orientasi->Tetap langsung tidak pernah "berkontrak", tidak ada periode buat dicatat.
            if ($newEmployeeType?->category === EmployeeTypeCategory::KONTRAK) {
                UserContract::create([
                    'user_id' => $user->id,
                    'company_id' => $transition->company_id,
                    'employee_type_id' => $newEmployeeType->id,
                    'contract_start_date' => $transition->contract_start_date,
                    'contract_end_date' => $transition->contract_end_date,
                    'source_transition_id' => $transition->id,
                ]);
            }

            return;
        }

        if ($type === UserTransitionTypeEnum::CONTRACT_EXTENSION->value) {
            // Perpanjangan Kontrak selalu tipe karyawan Kontrak (guard sudah cek ini di
            // CreateUserTransitionPage::submit()) - employee_type_id ambil dari kondisi
            // user SAAT INI, bukan transitionable_id (tidak dipakai buat jenis transisi ini).
            UserContract::create([
                'user_id' => $user->id,
                'company_id' => $transition->company_id,
                'employee_type_id' => $user->profile->employee_type_id,
                'contract_start_date' => $transition->contract_start_date,
                'contract_end_date' => $transition->contract_end_date,
                'source_transition_id' => $transition->id,
            ]);

            return;
        }

        if ($type === UserTransitionTypeEnum::TERMINATION->value) {
            $user->update(['is_active' => false]);

            $user->profile->update([
                'job_position_id' => null,
                'pangkat_id' => null,
                'kelas_id' => null,
                'org_structure_id' => null,
                'employee_type_id' => null,
            ]);

            $user->jobPositions()->whereNull('end_date')->update([
                'end_date' => now(),
                'status' => 'inactive',
            ]);
        }
    }
}
