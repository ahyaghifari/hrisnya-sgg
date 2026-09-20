<?php

namespace Tests\Feature\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Services\RoleAccess\PermissionTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Dashboard modul selalu tepat di bawah gerbang "Sistem X". */
class PortalGateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_permission_comes_right_after_module_gate(): void
    {
        Permission::query()->delete(); // buang seed migration supaya urutan id sepenuhnya dikontrol tes

        // Sengaja dibuat TERAKHIR (id terbesar) - urutan DB biasa menaruhnya paling bawah.
        foreach ([
            CustomPermissionEnum::SISTEM_KEUANGAN->value,
            CustomPermissionEnum::GENERATE_PAYROLL->value,
            CustomPermissionEnum::LIHAT_PAJAK->value,
            CustomPermissionEnum::LIHAT_DASHBOARD_KEUANGAN->value,
        ] as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        $root = collect(PermissionTreeBuilder::build())->firstWhere('id', 'root_sistem-keuangan');
        $gate = collect($root['children'])->firstWhere('id', 'gate_sistem-keuangan');

        $this->assertSame(
            [
                CustomPermissionEnum::SISTEM_KEUANGAN->value,
                CustomPermissionEnum::LIHAT_DASHBOARD_KEUANGAN->value,
                CustomPermissionEnum::GENERATE_PAYROLL->value,
                CustomPermissionEnum::LIHAT_PAJAK->value,
            ],
            array_column($gate['children'], 'id'),
        );
    }
}
