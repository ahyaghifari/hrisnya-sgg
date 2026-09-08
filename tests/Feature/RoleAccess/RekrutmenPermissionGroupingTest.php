<?php

namespace Tests\Feature\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Services\RoleAccess\PermissionTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Rekrutmen = modul sendiri (2026-08-31) — di tree /hak-akses jadi root "Sistem Rekrutmen"
 * berisi gerbang SISTEM_REKRUTMEN + subject "Kelola Lowongan" (Verb:JobOpenings).
 * INPUT_MCU_* pindah ke root "Sistem MCU" sendiri (2026-09-08), BUKAN lagi sub-grup Rekrutmen
 * maupun nyempil di "Sistem Kepegawaian" / "Sistem Sikartap".
 */
class RekrutmenPermissionGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            CustomPermissionEnum::SISTEM_REKRUTMEN->value,
            CustomPermissionEnum::SISTEM_MCU->value,
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

    public function test_sistem_rekrutmen_root_holds_lowongan_only(): void
    {
        $roots = PermissionTreeBuilder::build();

        $rekrutmen = collect($roots)->firstWhere('label', 'Sistem Rekrutmen');
        $this->assertNotNull($rekrutmen, 'Root "Sistem Rekrutmen" tidak ditemukan.');

        $leafIds = PermissionTreeBuilder::leafIds($rekrutmen);

        $this->assertContains(CustomPermissionEnum::SISTEM_REKRUTMEN->value, $leafIds);
        $this->assertContains('ViewAny:JobOpenings', $leafIds);
        $this->assertContains('Update:JobOpenings', $leafIds);
        $this->assertNotContains(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value, $leafIds);
        $this->assertNotContains(CustomPermissionEnum::EDIT_INPUT_MCU->value, $leafIds);

        $this->assertTrue(collect($rekrutmen['children'])->pluck('label')->contains('Kelola Lowongan'));
    }

    public function test_sistem_mcu_root_holds_input_mcu_permissions(): void
    {
        $roots = PermissionTreeBuilder::build();

        $mcu = collect($roots)->firstWhere('label', 'Sistem MCU');
        $this->assertNotNull($mcu, 'Root "Sistem MCU" tidak ditemukan.');

        $leafIds = PermissionTreeBuilder::leafIds($mcu);

        $this->assertContains(CustomPermissionEnum::SISTEM_MCU->value, $leafIds);
        $this->assertContains(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value, $leafIds);
        $this->assertContains(CustomPermissionEnum::EDIT_INPUT_MCU->value, $leafIds);
    }

    public function test_mcu_left_rekrutmen_kepegawaian_and_sikartap(): void
    {
        $roots = PermissionTreeBuilder::build();

        foreach (['Sistem Rekrutmen', 'Sistem Kepegawaian', 'Sistem Sikartap'] as $label) {
            $root = collect($roots)->firstWhere('label', $label);
            if ($root) {
                $this->assertNotContains(
                    CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value,
                    PermissionTreeBuilder::leafIds($root),
                    "MCU tidak boleh nyempil di \"{$label}\"."
                );
            }
        }

        $kepegawaian = collect($roots)->firstWhere('label', 'Sistem Kepegawaian');
        if ($kepegawaian) {
            $this->assertNotContains('ViewAny:JobOpenings', PermissionTreeBuilder::leafIds($kepegawaian));
        }
    }
}
