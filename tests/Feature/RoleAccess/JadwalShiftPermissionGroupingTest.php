<?php

namespace Tests\Feature\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Services\RoleAccess\PermissionTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * JADWAL_SHIFT_SAYA/TUKAR_SHIFT_SAYA (instruksi user 2026-08-17) - flat custom permission tanpa
 * ':' sebelumnya nyempil sendirian di section "Akses Lainnya", sekarang harus ikut masuk section
 * "Jadwal Shift" (tempat ShiftScheduleAssignments/ShiftSwapRequests/ShiftTemplates).
 */
class JadwalShiftPermissionGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_jadwal_shift_saya_and_tukar_shift_saya_land_in_jadwal_shift_container(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::JADWAL_SHIFT_SAYA->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::TUKAR_SHIFT_SAYA->value, 'guard_name' => 'web']);

        $roots = PermissionTreeBuilder::build();

        $jadwalShiftRoot = collect($roots)->firstWhere('label', 'Jadwal Shift');
        $this->assertNotNull($jadwalShiftRoot, 'Root "Jadwal Shift" tidak ditemukan di tree.');

        $leafIds = PermissionTreeBuilder::leafIds($jadwalShiftRoot);

        $this->assertContains(CustomPermissionEnum::JADWAL_SHIFT_SAYA->value, $leafIds);
        $this->assertContains(CustomPermissionEnum::TUKAR_SHIFT_SAYA->value, $leafIds);

        $lainnyaRoot = collect($roots)->firstWhere('label', 'Lainnya');
        if ($lainnyaRoot) {
            $lainnyaLeafIds = PermissionTreeBuilder::leafIds($lainnyaRoot);
            $this->assertNotContains(CustomPermissionEnum::JADWAL_SHIFT_SAYA->value, $lainnyaLeafIds);
            $this->assertNotContains(CustomPermissionEnum::TUKAR_SHIFT_SAYA->value, $lainnyaLeafIds);
        }
    }
}
