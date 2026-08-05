<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\JasaUnitInputPage;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\JasaUnitDetail;
use App\Models\Payroll\JasaUnitType;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur input Jasa Unit: pilih DIVISI dulu -> karyawan & jasa difilter ke divisi itu (2026-08-05,
 * gantiin alur lama "pilih karyawan -> jasa ke-filter dari divisi karyawan itu" yang kena bug
 * tom-select+wire:ignore "harus pilih karyawan 2x").
 */
class JasaUnitInputPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::EDIT_MAPPING_KOMPONEN_GAJI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Jasa Unit Input Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, CustomPermissionEnum::EDIT_MAPPING_KOMPONEN_GAJI->value]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function placeInDivision(User $user, Companies $company, OrgStructure $division): void
    {
        $jobPosition = JobPositions::create([
            'name' => 'Jabatan ' . $division->name,
            'company_id' => $company->id,
            'org_structure_id' => $division->id,
            'is_draft' => false,
        ]);

        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    public function test_employees_and_jasa_are_scoped_to_selected_division(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsFinance();

        $igd = OrgStructure::create(['company_id' => $company->id, 'name' => 'IGD', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $ambulan = OrgStructure::create(['company_id' => $company->id, 'name' => 'Ambulan', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::JASA_UNIT->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::JASA_UNIT->value, 'employee_type_id' => $employeeType->id]);

        $staffIgd = User::factory()->create(['company_id' => $company->id, 'name' => 'Staf IGD']);
        UserProfiles::create(['user_id' => $staffIgd->id, 'employee_type_id' => $employeeType->id]);
        $this->placeInDivision($staffIgd, $company, $igd);

        $staffAmbulan = User::factory()->create(['company_id' => $company->id, 'name' => 'Staf Ambulan']);
        UserProfiles::create(['user_id' => $staffAmbulan->id, 'employee_type_id' => $employeeType->id]);
        $this->placeInDivision($staffAmbulan, $company, $ambulan);

        $jasaPerujuk = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Perujuk Rawat']);
        $jasaPerujuk->orgStructures()->sync([$igd->id]);
        $jasaAmbulan = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan']);
        $jasaAmbulan->orgStructures()->sync([$ambulan->id]);

        $this->actingAs($viewer);
        $component = Livewire::test(JasaUnitInputPage::class)->set('companyId', $company->id);

        $employeesInIgd = $component->instance()->employeesFor($igd->id);
        $this->assertCount(1, $employeesInIgd);
        $this->assertSame('Staf IGD', $employeesInIgd->first()->name);

        $jasaInIgd = $component->instance()->jasaOptionsFor($igd->id);
        $this->assertCount(1, $jasaInIgd);
        $this->assertSame('Jasa Perujuk Rawat', $jasaInIgd->first()->name);

        // Belum pilih divisi -> kosong dua-duanya (memaksa urutan divisi dulu).
        $this->assertCount(0, $component->instance()->employeesFor(null));
        $this->assertCount(0, $component->instance()->jasaOptionsFor(null));
    }

    public function test_changing_division_resets_employee_and_jasa_selection_on_that_row(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsFinance();

        $igd = OrgStructure::create(['company_id' => $company->id, 'name' => 'IGD', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $ambulan = OrgStructure::create(['company_id' => $company->id, 'name' => 'Ambulan', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);

        $this->actingAs($viewer);

        Livewire::test(JasaUnitInputPage::class)
            ->set('companyId', $company->id)
            ->call('addRow')
            ->set('rows.0.user_id', 'some-user-id')
            ->set('rows.0.jasa_unit_type_id', 'some-jasa-id')
            ->set('rows.0.org_structure_id', $igd->id)
            ->assertSet('rows.0.user_id', null)
            ->assertSet('rows.0.jasa_unit_type_id', null)
            ->set('rows.0.org_structure_id', $ambulan->id)
            ->assertSet('rows.0.user_id', null)
            ->assertSet('rows.0.jasa_unit_type_id', null);
    }

    public function test_save_persists_row_and_ignores_org_structure_id(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsFinance();

        $igd = OrgStructure::create(['company_id' => $company->id, 'name' => 'IGD', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::JASA_UNIT->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::JASA_UNIT->value, 'employee_type_id' => $employeeType->id]);

        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id, 'employee_type_id' => $employeeType->id]);
        $this->placeInDivision($staff, $company, $igd);

        $jasa = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Perujuk Rawat']);
        $jasa->orgStructures()->sync([$igd->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $this->actingAs($viewer);

        Livewire::test(JasaUnitInputPage::class)
            ->set('companyId', $company->id)
            ->set('periodId', $period->id)
            ->call('addRow')
            ->set('rows.0.org_structure_id', $igd->id)
            ->set('rows.0.user_id', $staff->id)
            ->set('rows.0.jasa_unit_type_id', $jasa->id)
            ->set('rows.0.amount', '150000')
            ->call('save');

        $this->assertDatabaseHas('jasa_unit_details', [
            'payroll_period_id' => $period->id,
            'user_id' => $staff->id,
            'jasa_unit_type_id' => $jasa->id,
            'amount' => 150000,
        ]);
    }
}
