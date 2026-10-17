<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\KoperasiPage;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Employee\EmployeeKoperasi;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KoperasiPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:EmployeeKoperasi', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:EmployeeKoperasi', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:EmployeeKoperasi', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:EmployeeKoperasi', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Koperasi Test', 'guard_name' => 'web']);
        $role->givePermissionTo([
            CustomPermissionEnum::SISTEM_KEUANGAN->value,
            'ViewAny:EmployeeKoperasi',
            'Create:EmployeeKoperasi',
            'Update:EmployeeKoperasi',
            'Delete:EmployeeKoperasi',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Company TST', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(KoperasiPage::class)
            ->assertForbidden();
    }

    public function test_can_add_member(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        UserProfiles::create(['user_id' => $employee->id, 'employee_type_id' => $employeeType->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KOPERASI->value, 'employee_type_id' => $employeeType->id]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->call('openAddModal')
            ->set('addEmployeeId', $employee->employee->id)
            ->set('addAmount', '150000')
            ->call('saveAdd');

        $this->assertDatabaseHas('employee_koperasi', ['employee_id' => $employee->employee->id, 'is_active' => true]);
        $this->assertEquals(150000, EmployeeKoperasi::where('employee_id', $employee->employee->id)->firstOrFail()->amount); // kolom amount terenkripsi
    }

    public function test_bulk_edit_amount_updates_selected_members(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employeeA = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $employeeB = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $memberA = EmployeeKoperasi::create(['employee_id' => $employeeA->employee->id, 'amount' => 100000, 'is_active' => true]);
        $memberB = EmployeeKoperasi::create(['employee_id' => $employeeB->employee->id, 'amount' => 200000, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->set('selected', [$memberA->id, $memberB->id])
            ->call('openEditModal')
            ->set('editAmount', '300000')
            ->call('saveEdit');

        $this->assertEquals(300000, $memberA->fresh()->amount);
        $this->assertEquals(300000, $memberB->fresh()->amount);
    }

    public function test_toggle_active_flips_status(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $member = EmployeeKoperasi::create(['employee_id' => $employee->employee->id, 'amount' => 100000, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->call('toggleActive', $member->id);

        $this->assertDatabaseHas('employee_koperasi', ['id' => $member->id, 'is_active' => false]);
    }

    public function test_remove_member_deletes_row(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $member = EmployeeKoperasi::create(['employee_id' => $employee->employee->id, 'amount' => 100000, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->call('removeMember', $member->id);

        $this->assertDatabaseMissing('employee_koperasi', ['id' => $member->id]);
    }

    public function test_export_returns_download_response(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        EmployeeKoperasi::create(['employee_id' => $employee->employee->id, 'amount' => 100000, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->call('export')
            ->assertFileDownloaded();
    }
}
