<?php

namespace Tests\Feature;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalPageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('organization-structure'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_forbidden_from_organization_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('organization-structure'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_organization_structure(): void
    {
        Permission::create(['name' => 'view_struktur_organisasi', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'HC Manager', 'guard_name' => 'web']);
        $role->givePermissionTo('view_struktur_organisasi');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('organization-structure'))
            ->assertOk();
    }

    public function test_employee_without_subordinates_is_forbidden_from_karyawanku(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('my-staff'))
            ->assertForbidden();
    }

    public function test_manager_with_subordinates_can_access_karyawanku(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        UserJobPositions::create([
            'user_id' => $manager->id,
            'job_position_id' => $managerPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->get(route('my-staff'))
            ->assertOk();
    }
}
