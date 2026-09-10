<?php

namespace Tests\Feature;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Master\Pangkats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeFormTest extends TestCase
{
    use RefreshDatabase;

    private function fieldByPathSuffix($schema, string $suffix)
    {
        $flat = $schema->getFlatFields(withHidden: true);
        $key = collect(array_keys($flat))->first(fn ($k) => str_ends_with($k, $suffix));

        return $flat[$key] ?? null;
    }

    public function test_step3_dropdowns_are_scoped_to_the_selected_company_not_the_logged_in_admins(): void
    {
        // Admin global beneran: di app ini "global" = company_id sama dengan ulid
        // "Global Company" (lihat PermissionHelper::hasGlobalRole), bukan company_id null.
        // 'id' bukan fillable (HasUlids ngisi otomatis di creating()), jadi paksa pakai forceCreate.
        Companies::forceCreate(['id' => '01J2F0C8KQ9M7D4S8RBX6E1ZVP', 'code' => 'GLB', 'name' => 'Global Company', 'location' => 'Global', 'is_active' => true]);
        $admin = User::factory()->create(['company_id' => '01J2F0C8KQ9M7D4S8RBX6E1ZVP']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        JobPositions::create(['name' => 'Staff', 'company_id' => $company->id]);
        Pangkats::create(['name' => 'Perawat', 'golongan' => 'III/a', 'company_id' => $company->id]);
        Kelas::create(['name' => 'Kelas A', 'company_id' => $company->id]);
        OrgStructure::create(['name' => 'Departemen A', 'company_id' => $company->id, 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);

        $livewire = Livewire::actingAs($admin)
            ->test(CreateEmployee::class)
            ->set('data.company_id', $company->id);

        $schema = $livewire->instance()->getSchema('form');

        $this->assertNotEmpty(
            $this->fieldByPathSuffix($schema, 'job_position_id')->getSearchResults('Staff'),
            'Jabatan harusnya nemu "Staff" di company yang dipilih.'
        );
        $this->assertNotEmpty(
            $this->fieldByPathSuffix($schema, 'pangkat_id')->getSearchResults('Perawat'),
            'Pangkat harusnya nemu "Perawat" di company yang dipilih.'
        );
        $this->assertNotEmpty(
            $this->fieldByPathSuffix($schema, 'kelas_id')->getSearchResults('Kelas'),
            'Kelas harusnya nemu "Kelas A" di company yang dipilih.'
        );

        $departmentOptions = $this->fieldByPathSuffix($schema, 'department_id')->getOptions();
        $this->assertNotEmpty($departmentOptions, 'Departemen harusnya nemu "Departemen A" di company yang dipilih.');
    }

    public function test_can_create_employee_with_multiple_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $roleA = Role::firstOrCreate(['name' => 'Manajer', 'guard_name' => 'web', 'company_id' => $company->id]);
        $roleB = Role::firstOrCreate(['name' => 'Keuangan', 'guard_name' => 'web', 'company_id' => $company->id]);

        Livewire::actingAs($admin)
            ->test(CreateEmployee::class)
            ->fillForm([
                'name' => 'Karyawan Test',
                'email' => 'karyawan-test@example.com',
                'nip' => '999888777',
                'password' => 'password123',
                'company_id' => $company->id,
                'role_ids' => [$roleA->id, $roleB->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'karyawan-test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Manajer'));
        $this->assertTrue($user->hasRole('Keuangan'));
        $this->assertCount(2, $user->roles);
    }
}
