<?php

namespace Tests\Feature\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Services\RoleAccess\PermissionTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Rekrutmen = modul sendiri (2026-08-31) — di tree /hak-akses jadi root "Sistem Rekrutmen"
 * berisi gerbang SISTEM_REKRUTMEN + subject "Kelola Lowongan" (Verb:JobOpenings) + sub-grup
 * "Input MCU" (INPUT_MCU_*), BUKAN lagi nyempil di "Sistem Kepegawaian" / "Sistem Sikartap".
 */
class RekrutmenPermissionGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            CustomPermissionEnum::SISTEM_REKRUTMEN->value,
            CustomPermissionEnum::SISTEM_SIKARTAP->value,
            CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value,
            CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value,
            CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value,
            CustomPermissionEnum::EDIT_INPUT_MCU->value,
            'ViewAny:JobOpenings',
            'View:JobOpenings',
            'Update:JobOpenings',
        ] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_sistem_rekrutmen_root_holds_lowongan_and_mcu(): void
    {
        $roots = PermissionTreeBuilder::build();

        $rekrutmen = collect($roots)->firstWhere('label', 'Sistem Rekrutmen');
        $this->assertNotNull($rekrutmen, 'Root "Sistem Rekrutmen" tidak ditemukan.');

        $leafIds = PermissionTreeBuilder::leafIds($rekrutmen);

        $this->assertContains(CustomPermissionEnum::SISTEM_REKRUTMEN->value, $leafIds);
        $this->assertContains('ViewAny:JobOpenings', $leafIds);
        $this->assertContains('Update:JobOpenings', $leafIds);
        $this->assertContains(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value, $leafIds);
        $this->assertContains(CustomPermissionEnum::EDIT_INPUT_MCU->value, $leafIds);

        // "Kelola Lowongan" sebagai label subject node.
        $subjectLabels = collect($rekrutmen['children'])->pluck('label');
        $this->assertTrue($subjectLabels->contains('Kelola Lowongan'));
        $this->assertTrue($subjectLabels->contains('Input MCU'));
    }

    public function test_job_openings_and_mcu_left_kepegawaian_and_sikartap(): void
    {
        $roots = PermissionTreeBuilder::build();

        $kepegawaian = collect($roots)->firstWhere('label', 'Sistem Kepegawaian');
        if ($kepegawaian) {
            $this->assertNotContains('ViewAny:JobOpenings', PermissionTreeBuilder::leafIds($kepegawaian));
        }

        $sikartap = collect($roots)->firstWhere('label', 'Sistem Sikartap');
        if ($sikartap) {
            $this->assertNotContains(
                CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value,
                PermissionTreeBuilder::leafIds($sikartap)
            );
        }
    }
}
