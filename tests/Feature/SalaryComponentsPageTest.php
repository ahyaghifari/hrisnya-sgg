<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\SalaryComponentsPage;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reganti SalaryComponentCrudTest.php lama (Filament Resource + tabel salary_components) —
 * dihapus total di Fase 1 (commit 7fe29ec), diganti PayrollComponentEnum (vocabulary tertutup)
 * + tabel company_payroll_components (saklar ON/OFF), lihat SalaryComponentsPage.
 */
class SalaryComponentsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEUANGAN->value);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->makeCompany();

        Livewire::actingAs($user)
            ->test(SalaryComponentsPage::class)
            ->assertForbidden();
    }

    public function test_toggling_component_on_and_saving_persists_registry_row(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();

        Livewire::actingAs($finance)
            ->test(SalaryComponentsPage::class)
            ->call('selectCompany', $company->id)
            ->set('active.' . PayrollComponentEnum::GAJI_POKOK->value, true)
            ->call('save');

        $this->assertDatabaseHas('company_payroll_components', [
            'company_id' => $company->id,
            'component' => PayrollComponentEnum::GAJI_POKOK->value,
            'is_active' => true,
        ]);
    }

    public function test_toggling_component_off_and_saving_updates_existing_row(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        CompanyPayrollComponent::create([
            'company_id' => $company->id,
            'component' => PayrollComponentEnum::GAJI_POKOK->value,
            'is_active' => true,
        ]);

        Livewire::actingAs($finance)
            ->test(SalaryComponentsPage::class)
            ->call('selectCompany', $company->id)
            ->set('active.' . PayrollComponentEnum::GAJI_POKOK->value, false)
            ->call('save');

        $this->assertDatabaseHas('company_payroll_components', [
            'company_id' => $company->id,
            'component' => PayrollComponentEnum::GAJI_POKOK->value,
            'is_active' => false,
        ]);
    }

    public function test_toggles_are_scoped_per_company(): void
    {
        $finance = $this->actingAsFinance();
        $companyA = $this->makeCompany();
        $companyB = Companies::create(['code' => 'TS2', 'name' => 'Test Company B', 'location' => 'Test', 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(SalaryComponentsPage::class)
            ->call('selectCompany', $companyA->id)
            ->set('active.' . PayrollComponentEnum::GAJI_POKOK->value, true)
            ->call('save')
            ->call('selectCompany', $companyB->id)
            ->assertSet('active.' . PayrollComponentEnum::GAJI_POKOK->value, false);

        $this->assertDatabaseMissing('company_payroll_components', [
            'company_id' => $companyB->id,
            'component' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);
    }
}
