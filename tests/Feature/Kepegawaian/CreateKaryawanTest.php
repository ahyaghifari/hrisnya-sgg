<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Kepegawaian\CreateKaryawan;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class CreateKaryawanTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsKepegawaian(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:User', 'guard_name' => 'web']);
        $role = SpatieRole::firstOrCreate(['name' => 'Kepegawaian Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'Create:User']);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(CreateKaryawan::class)->assertForbidden();
    }

    public function test_next_step_blocks_when_required_fields_missing(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $this->actingAs($hc);

        Livewire::test(CreateKaryawan::class)
            ->call('nextStep')
            ->assertHasErrors(['name', 'email', 'password', 'role_ids'])
            ->assertSet('step', 1);
    }

    public function test_full_wizard_creates_employee_with_role(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employeeRole = \App\Models\Role::create(['name' => 'Staff Baru', 'guard_name' => 'web', 'company_id' => $company->id]);

        $this->actingAs($hc);

        Livewire::test(CreateKaryawan::class)
            ->set('name', 'Karyawan Baru')
            ->set('email', 'karyawan.baru@test.com')
            ->set('password', 'password123')
            ->set('company_id', $company->id)
            ->set('role_ids', [$employeeRole->id])
            ->call('nextStep')
            ->assertSet('step', 2)
            ->call('nextStep')
            ->assertSet('step', 3)
            ->call('save');

        $created = User::where('email', 'karyawan.baru@test.com')->first();

        $this->assertNotNull($created);
        $this->assertEquals($company->id, $created->company_id);
        $this->assertTrue($created->hasRole('Staff Baru'));
    }

    public function test_save_blocked_when_job_position_quota_full(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employeeRole = \App\Models\Role::create(['name' => 'Staff Kuota', 'guard_name' => 'web', 'company_id' => $company->id]);

        $jobPosition = JobPositions::create([
            'company_id' => $company->id,
            'name' => 'Posisi Penuh',
            'planned_manpower' => 1,
            'actual_manpower' => 1,
        ]);

        $existingHolder = User::factory()->create(['company_id' => $company->id]);
        \App\Models\User\UserJobPositions::create([
            'user_id' => $existingHolder->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($hc);

        Livewire::test(CreateKaryawan::class)
            ->set('name', 'Karyawan Gagal')
            ->set('email', 'karyawan.gagal@test.com')
            ->set('password', 'password123')
            ->set('company_id', $company->id)
            ->set('role_ids', [$employeeRole->id])
            ->set('job_position_id', $jobPosition->id)
            ->call('save')
            ->assertHasErrors(['job_position_id']);

        $this->assertNull(User::where('email', 'karyawan.gagal@test.com')->first());
    }
}
