<?php

namespace Tests\Support;

use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
use App\Models\Sp\EmployeeSp;
use App\Models\User;
use App\Services\Sp\SpService;

/**
 * SP DRAFT langsung lewat model, tanpa halaman pembuat - jalur "SP + Berita Acara lama" (BA
 * status NULL, bagian dari SP) yang sudah tidak punya halaman pembuat sejak SP wajib lewat BA
 * (instruksi user 2026-10-01), tapi datanya masih ada & masih ditandatangani/diverifikasi/dicetak.
 * Dipakai test yang menguji perilaku data lama itu.
 */
class SpFixtures
{
    /** Jadikan $user pemegang jabatan aktif di level Departemen (syarat penanda tangan "Mengetahui"). */
    public static function makeDepartmentHead(User $user): void
    {
        $department = \App\Models\Master\OrgStructure::create([
            'company_id' => $user->company_id, 'name' => 'Dept ' . $user->id, 'level' => \App\Enum\OrgStructurLevel::LEVEL_DEPARTMENT->value,
        ]);
        $position = \App\Models\Master\JobPositions::create([
            'company_id' => $user->company_id, 'name' => 'Manager ' . $user->id, 'planned_manpower' => 1, 'org_structure_id' => $department->id,
        ]);
        \App\Models\User\UserJobPositions::create([
            'user_id' => $user->id, 'job_position_id' => $position->id, 'start_date' => now()->subYear(), 'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, string>  $report  kolom Berita Acara lama (kejadian, dasar_klarifikasi, ...); kosong = tanpa BA
     */
    public static function draftSp(User $hc, string $companyId, string $employeeId, string $knowerId, string $nomor, array $report = []): EmployeeSp
    {
        $sp = EmployeeSp::create([
            'nomor' => $nomor,
            'user_id' => $employeeId,
            'company_id' => $companyId,
            'sp_level' => SpLevel::SP1->value,
            'source_type' => SpSourceType::MANUAL->value,
            'start_date' => now()->toDateString(),
            'end_date' => app(SpService::class)->resolveEndDate(SpLevel::SP1, $companyId, now()->toDateString()),
            'created_by' => $hc->id,
            'knows_by' => $knowerId,
        ]);

        $sp->items()->create(['text' => 'Pelanggaran', 'sort_order' => 0]);

        if ($report) {
            $sp->report()->create($report);
        }

        return $sp;
    }
}
