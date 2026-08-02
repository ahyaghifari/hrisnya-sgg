<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\SalaryComponentsPage;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
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
        Permission::firstOrCreate(['name' => CustomPermissionEnum::EDIT_KOMPONEN_GAJI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, CustomPermissionEnum::EDIT_KOMPONEN_GAJI->value]);

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

    public function test_switching_bon_metode_to_simple_consolidates_leftover_detail_lines(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id, 'is_paid' => false]);

        // 3 baris sisa dari mode 'detail'.
        PayrollManualLine::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'component' => 'bon', 'amount' => 200000, 'note' => 'Bon tgl 5']);
        PayrollManualLine::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'component' => 'bon', 'amount' => 150000, 'note' => 'Ganti kerugian']);
        PayrollManualLine::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'component' => 'bon', 'amount' => 50000, 'note' => 'Titip beli']);

        Livewire::actingAs($finance)
            ->test(SalaryComponentsPage::class)
            ->call('selectCompany', $company->id)
            ->call('toggleExpand', PayrollComponentEnum::BON->value)
            ->set('settingValues.bon_metode', 'simple')
            ->call('save');

        $lines = PayrollManualLine::where('payroll_period_id', $period->id)->where('component', 'bon')->get();

        $this->assertCount(1, $lines);
        $this->assertSame(400000.0, (float) $lines->first()->amount);
    }

    public function test_switching_bon_metode_to_simple_skips_paid_period(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id, 'is_paid' => true]);

        PayrollManualLine::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'component' => 'bon', 'amount' => 200000]);
        PayrollManualLine::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'component' => 'bon', 'amount' => 150000]);

        Livewire::actingAs($finance)
            ->test(SalaryComponentsPage::class)
            ->call('selectCompany', $company->id)
            ->call('toggleExpand', PayrollComponentEnum::BON->value)
            ->set('settingValues.bon_metode', 'simple')
            ->call('save');

        // Periode sudah dibayar - baris historisnya dibiarkan apa adanya, tetap 2 baris.
        $this->assertCount(2, PayrollManualLine::where('payroll_period_id', $period->id)->where('component', 'bon')->get());
    }
}
