<?php

namespace App\Repositories\Approval;

use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\JobPositions;
use App\Services\Identity\EmployeeContext;

class ApprovalSnapshotRepository
{
    public function create(array $data): ApprovalSnapshots
    {
        return ApprovalSnapshots::create($data);
    }

    public function findByModule(string $moduleType, string $moduleId): ?ApprovalSnapshots
    {
        return ApprovalSnapshots::with('activity')->where([
            'module_type' => $moduleType,
            'module_id'   => $moduleId,
        ])->first();
    }

    public function update(ApprovalSnapshots $snapshot, array $data): ApprovalSnapshots
    {
        $snapshot->update($data);
        return $snapshot->fresh();
    }

    /**
     * Jabatan atasan pengaju: naik $level tingkat dari jabatan pengaju lewat JobPositions.parent_id
     * (1 = atasan langsung, 2 = atasan-nya atasan, dst). Dipakai buat membekukan step
     * approver_type 'supervisor' jadi 'job_position' saat snapshot.
     * null kalau pengaju tidak punya jabatan, atau rantai jabatan lebih pendek dari $level.
     */
    public function resolveSupervisorPositionId(?string $requesterUserId, int $level = 1): ?string
    {
        if (! $requesterUserId || $level < 1) {
            return null;
        }

        $currentId = EmployeeContext::jobPositionId($requesterUserId);

        if (! $currentId) {
            return null;
        }

        for ($i = 0; $i < $level; $i++) {
            $currentId = JobPositions::where('id', $currentId)->value('parent_id');

            if (! $currentId) {
                return null;
            }
        }

        return $currentId;
    }

    public function findMasterApprovalFlowByCompany(string $moduleType, string $companyId)
    {
        return MasterApprovalFlows::where('module', $moduleType)
            ->where('is_active', true)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * @param string|null $subModule "Ember" jenis pengajuan (AttendanceApprovalBucketEnum::value)
     *   buat modul attendance_request. Alur yang kolom `sub_module`-nya memuat ember ini
     *   (daftar dipisah koma) diprioritaskan; kalau tidak ada, fallback ke alur `sub_module`
     *   NULL persis seperti perilaku lama. Pemanggil modul lain kirim null → jalur lama.
     */
    public function resolveApprovalFlowForUser(
        string $moduleType,
        string $companyId,
        string $userId,
        ?string $subModule = null
    ): ?MasterApprovalFlows {
        // Ambil jabatan user dari data kepegawaian (employees, jatuh balik ke profil)
        $jobPositionId = EmployeeContext::jobPositionId($userId);

        // Cocok kalau baris "berlaku semua jenis" (sub_module kosong) saat $sm null, atau
        // ember $sm ada di dalam daftar koma. Filter di PHP: tabel kecil (segelintir baris per
        // company) & FIND_IN_SET tidak ada di SQLite (test).
        $matchesSubModule = function (?string $listStr, ?string $sm): bool {
            if ($sm === null) {
                return $listStr === null || $listStr === '';
            }

            return $listStr !== null && in_array($sm, explode(',', $listStr), true);
        };

        // Urutan: [subModule] specific > [subModule] default > [null] specific > [null] default
        $candidates = $subModule !== null ? [$subModule, null] : [null];

        foreach ($candidates as $sm) {
            if ($jobPositionId) {
                $specificFlow = MasterApprovalFlows::with('steps')
                    ->where('module', $moduleType)
                    ->where('company_id', $companyId)
                    ->where('flow_type', 'specific')
                    ->where('is_active', true)
                    ->whereHas('assignments', function ($q) use ($jobPositionId) {
                        $q->where('assignable_type', JobPositions::class)
                          ->where('assignable_id', $jobPositionId);
                    })
                    ->get()
                    ->first(fn (MasterApprovalFlows $f) => $matchesSubModule($f->sub_module, $sm));

                if ($specificFlow) {
                    return $specificFlow;
                }
            }

            $defaultFlow = MasterApprovalFlows::with('steps')
                ->where('module', $moduleType)
                ->where('company_id', $companyId)
                ->where('flow_type', 'default')
                ->where('is_active', true)
                ->get()
                ->first(fn (MasterApprovalFlows $f) => $matchesSubModule($f->sub_module, $sm));

            if ($defaultFlow) {
                return $defaultFlow;
            }
        }

        return null;
    }
}
