<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Kepegawaian\AlurPersetujuanListPage;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurPersetujuanListPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsWithPermission(Companies $company): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_ALUR_PERSETUJUAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Alur Persetujuan List Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::LIHAT_ALUR_PERSETUJUAN->value);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'APL', 'name' => 'RS List Test', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(AlurPersetujuanListPage::class)
            ->assertForbidden();
    }

    public function test_company_scoped_user_only_sees_own_company_flows(): void
    {
        $company = Companies::create(['code' => 'APL2', 'name' => 'RS List Test 2', 'location' => 'Test', 'is_active' => true]);
        $otherCompany = Companies::create(['code' => 'APL3', 'name' => 'RS List Test 3', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);

        MasterApprovalFlows::create(['company_id' => $company->id, 'name' => 'Alur Saya', 'module' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value, 'flow_type' => 'default', 'is_active' => true]);
        MasterApprovalFlows::create(['company_id' => $otherCompany->id, 'name' => 'Alur Company Lain', 'module' => ApprovalModuleEnum::ATTENDANCE_REQUEST->value, 'flow_type' => 'default', 'is_active' => true]);

        $component = Livewire::actingAs($user)->test(AlurPersetujuanListPage::class);

        $flows = $component->get('flows');

        $this->assertCount(1, $flows);
        $this->assertSame('Alur Saya', $flows->first()->name);
    }
}
