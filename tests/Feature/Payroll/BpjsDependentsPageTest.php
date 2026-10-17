<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\BpjsDependentsPage;
use App\Models\Employee\EmployeeBpjsDependent;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BpjsDependentsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        $names = [CustomPermissionEnum::SISTEM_KEUANGAN->value, 'ViewAny:EmployeeBpjsDependent', 'Create:EmployeeBpjsDependent', 'Update:EmployeeBpjsDependent'];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Keuangan BPJS Test', 'guard_name' => 'web']);
        $role->givePermissionTo($names);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(BpjsDependentsPage::class)
            ->assertForbidden();
    }

    public function test_can_add_dependent_and_duplicate_is_rejected(): void
    {
        $finance = $this->actingAsFinance();
        $company = Companies::create(['code' => 'TST', 'name' => 'Company TST', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        $component = Livewire::actingAs($finance)
            ->test(BpjsDependentsPage::class)
            ->set('companyId', $company->id)
            ->call('openAddModal')
            ->set('addEmployeeId', $employee->employee->id)
            ->set('addCount', '2')
            ->call('saveAdd');

        $this->assertDatabaseHas('employee_bpjs_dependents', ['employee_id' => $employee->employee->id, 'count' => 2, 'is_active' => true]);

        $component->call('openAddModal')
            ->set('addEmployeeId', $employee->employee->id)
            ->set('addCount', '3')
            ->call('saveAdd')
            ->assertStatus(409);

        $this->assertSame(1, EmployeeBpjsDependent::count());
    }
}
