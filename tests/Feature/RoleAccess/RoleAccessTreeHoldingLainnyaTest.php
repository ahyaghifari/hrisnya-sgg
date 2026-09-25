<?php

namespace Tests\Feature\RoleAccess;

use App\Enum\CustomPermissionEnum;
use App\Livewire\RoleAccess\RoleAccessTreePage;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Root "Lainnya" di tree /hak-akses (permission tanpa container/kategori jelas, lihat
 * PermissionTreeBuilder) cuma boleh dilihat/diatur user yang login dari company holding
 * (code '0000', lihat Companies::HOLDING_CODE) - siapapun role/user yang lagi diedit.
 */
class RoleAccessTreeHoldingLainnyaTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $companyCode): array
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::KELOLA_HAK_AKSES->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'permission_ngawur_tanpa_grup', 'guard_name' => 'web']);

        $company = Companies::create(['code' => $companyCode, 'name' => "Company {$companyCode}", 'location' => 'Test', 'is_active' => true]);
        $role = Role::firstOrCreate(['name' => "Admin {$companyCode}", 'guard_name' => 'web', 'company_id' => $company->id]);
        $role->givePermissionTo(CustomPermissionEnum::KELOLA_HAK_AKSES->value);

        $editableRole = Role::create(['name' => 'Editable Role', 'guard_name' => 'web', 'company_id' => $company->id]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole($role);

        return [$admin, $company, $editableRole];
    }

    public function test_holding_admin_sees_lainnya_root_node(): void
    {
        [$admin, , $editableRole] = $this->makeAdmin(Companies::HOLDING_CODE);

        $tree = Livewire::actingAs($admin)
            ->test(RoleAccessTreePage::class)
            ->set('roleId', $editableRole->id)
            ->get('tree');

        $lainnya = collect($tree)->firstWhere('id', 'subject_lainnya');

        $this->assertNotNull($lainnya, 'Root "Lainnya" harusnya tampil buat admin company holding.');
    }

    public function test_non_holding_admin_does_not_see_lainnya_root_node(): void
    {
        [$admin, , $editableRole] = $this->makeAdmin('0001');

        $tree = Livewire::actingAs($admin)
            ->test(RoleAccessTreePage::class)
            ->set('roleId', $editableRole->id)
            ->get('tree');

        $lainnya = collect($tree)->firstWhere('id', 'subject_lainnya');

        $this->assertNull($lainnya, 'Root "Lainnya" harusnya disembunyikan buat admin bukan company holding.');
    }

    public function test_saving_role_from_non_holding_admin_does_not_strip_existing_lainnya_permission(): void
    {
        [$admin, , $editableRole] = $this->makeAdmin('0001');

        // Role sudah punya permission "Lainnya" dari sebelumnya (mis. diberi lewat admin holding).
        $editableRole->givePermissionTo('permission_ngawur_tanpa_grup');

        Livewire::actingAs($admin)
            ->test(RoleAccessTreePage::class)
            ->set('roleId', $editableRole->id)
            ->call('save', []);

        $this->assertTrue(
            $editableRole->fresh()->hasPermissionTo('permission_ngawur_tanpa_grup'),
            'Permission "Lainnya" yang sudah ada harusnya tetap dipertahankan walau disimpan oleh admin non-holding.'
        );
    }
}
