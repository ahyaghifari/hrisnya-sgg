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
use App\Models\Payroll\PayrollJasaUnitDetail;
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
 * Alur input Jasa Unit: pilih KARYAWAN dulu (daftar penuh, tidak difilter divisi) -> jasa
 * difilter OTOMATIS dari divisi jabatan aktif karyawan itu (2026-08-17, gantiin alur lama
 * "pilih divisi -> karyawan & jasa difilter ke divisi itu", instruksi user).
 */
class JasaUnitInputPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(Companies $company): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::KELOLA_INPUT_JASA_UNIT->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Jasa Unit Input Test', 'guard_name' => 'web']);
        $role->givePermissionTo([
            CustomPermissionEnum::SISTEM_KEUANGAN->value,
            CustomPermissionEnum::LIHAT_INPUT_JASA_UNIT->value,
            CustomPermissionEnum::KELOLA_INPUT_JASA_UNIT->value,
        ]);

        // company_id di-set langsung di user (bukan lewat ->set('companyId', ...) di test) -
        // canFilterByCompany() false buat user ini, jadi updatedCompanyId() akan MEMAKSA balik
        // ke company_id user sendiri kalau di-set manual (lihat JasaUnitInputPage::updatedCompanyId()).
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    private function placeInDivision(User $user, Companies $company, OrgStructure $division): void
    {
        $jobPosition = JobPositions::create([
            'name' => 'Jabatan ' . $division->name,
            'company_id' => $company->id,
            'org_structure_id' => $division->id,
            'status' => 'approved',
        ]);

        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    public function test_eligible_employees_list_is_not_scoped_to_any_division(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsFinance($company);

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

        $component = Livewire::actingAs($viewer)->test(JasaUnitInputPage::class);

        $employees = $component->instance()->eligibleEmployees;
        $this->assertCount(2, $employees);
    }

    public function test_jasa_options_are_scoped_to_selected_employees_division(): void
    {
        $company = Companies::create(['code' => 'TST2', 'name' => 'Test Company 2', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsFinance($company);

        $igd = OrgStructure::create(['company_id' => $company->id, 'name' => 'IGD', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $ambulan = OrgStructure::create(['company_id' => $company->id, 'name' => 'Ambulan', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        $staffIgd = User::factory()->create(['company_id' => $company->id, 'name' => 'Staf IGD']);
        UserProfiles::create(['user_id' => $staffIgd->id, 'employee_type_id' => $employeeType->id]);
        $this->placeInDivision($staffIgd, $company, $igd);

        $jasaPerujuk = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Perujuk Rawat', 'code' => 'JU-1']);
        $jasaPerujuk->orgStructures()->sync([$igd->id]);
        $jasaAmbulan = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-2']);
        $jasaAmbulan->orgStructures()->sync([$ambulan->id]);

        $component = Livewire::actingAs($viewer)->test(JasaUnitInputPage::class);

        $jasaForStaffIgd = $component->instance()->jasaOptionsFor($staffIgd->employee->id);
        $this->assertCount(1, $jasaForStaffIgd);
        $this->assertSame('Jasa Perujuk Rawat', $jasaForStaffIgd->first()->name);

        // Belum pilih karyawan -> kosong (memaksa urutan karyawan dulu).
        $this->assertCount(0, $component->instance()->jasaOptionsFor(null));
    }

    public function test_changing_employee_resets_jasa_selection_on_that_row(): void
    {
        $company = Companies::create(['code' => 'TST3', 'name' => 'Test Company 3', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsFinance($company);

        Livewire::actingAs($viewer)->test(JasaUnitInputPage::class)
            ->call('addRow')
            ->set('rows.0.jasa_unit_type_id', 'some-jasa-id')
            ->set('rows.0.employee_id', 'some-employee-id')
            ->assertSet('rows.0.jasa_unit_type_id', null);
    }

    public function test_save_persists_row(): void
    {
        $company = Companies::create(['code' => 'TST4', 'name' => 'Test Company 4', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsFinance($company);

        $igd = OrgStructure::create(['company_id' => $company->id, 'name' => 'IGD', 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::JASA_UNIT->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::JASA_UNIT->value, 'employee_type_id' => $employeeType->id]);

        $staff = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $staff->id, 'employee_type_id' => $employeeType->id]);
        $this->placeInDivision($staff, $company, $igd);

        $jasa = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Perujuk Rawat', 'code' => 'JU-1']);
        $jasa->orgStructures()->sync([$igd->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($viewer)->test(JasaUnitInputPage::class)
            ->set('periodId', $period->id)
            ->call('addRow')
            ->set('rows.0.employee_id', $staff->employee->id)
            ->set('rows.0.jasa_unit_type_id', $jasa->id)
            ->set('rows.0.amount', '150000')
            ->call('save');

        $detail = PayrollJasaUnitDetail::where('payroll_period_id', $period->id)
            ->where('employee_id', $staff->employee->id)
            ->where('jasa_unit_type_id', $jasa->id)
            ->first();
        $this->assertNotNull($detail);
        $this->assertSame(150000.0, (float) $detail->amount);
    }
}
