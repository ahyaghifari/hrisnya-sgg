<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\ExecutiveEmployees\ExecutiveEmployeeResource;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Data Eksekutif (admin panel) - hanya Super Admin, walau role lain punya ViewAny:User/Update:User. */
class ExecutiveEmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName, array $permissions = []): User
    {
        $company = Companies::firstOrCreate(['code' => 'TST'], ['name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->givePermissionTo($name);
        }

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_non_super_admin_with_user_permissions_cannot_access_data_eksekutif(): void
    {
        $hc = $this->userWithRole('HC Test', [
            CustomPermissionEnum::AKSES_PANEL_ADMIN->value, 'ViewAny:User', 'View:User', 'Update:User',
        ]);

        $this->actingAs($hc);

        $this->assertFalse(ExecutiveEmployeeResource::canViewAny());
        $this->assertFalse(ExecutiveEmployeeResource::canEdit(new User));
        $this->assertFalse(ExecutiveEmployeeResource::shouldRegisterNavigation() && ExecutiveEmployeeResource::canAccess());

        $this->get('/admin/executive-employees')->assertForbidden();
    }

    public function test_admin_role_is_also_blocked(): void
    {
        $admin = $this->userWithRole('Admin', ['ViewAny:User', 'Update:User']);

        $this->actingAs($admin);

        $this->assertFalse(ExecutiveEmployeeResource::canViewAny());
        $this->get('/admin/executive-employees')->assertForbidden();
    }

    public function test_super_admin_can_access_data_eksekutif(): void
    {
        $super = $this->userWithRole('Super Admin');

        $this->actingAs($super);

        $this->assertTrue(ExecutiveEmployeeResource::canViewAny());
        $this->assertTrue(ExecutiveEmployeeResource::canEdit(new User));
        $this->assertTrue(ExecutiveEmployeeResource::canAccess());
        $this->assertFalse(ExecutiveEmployeeResource::canCreate()); // tetap tidak ada Create
    }
}
