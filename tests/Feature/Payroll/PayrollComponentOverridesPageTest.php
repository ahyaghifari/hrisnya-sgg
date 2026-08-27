<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\PayrollComponentOverridesPage;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollComponentOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollComponentOverridesPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(bool $canEdit = true): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_OVERRIDE_KOMPONEN_GAJI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::EDIT_OVERRIDE_KOMPONEN_GAJI->value, 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'Finance Override Test', 'guard_name' => 'web']);
        $permissions = [CustomPermissionEnum::LIHAT_OVERRIDE_KOMPONEN_GAJI->value];
        if ($canEdit) {
            $permissions[] = CustomPermissionEnum::EDIT_OVERRIDE_KOMPONEN_GAJI->value;
        }
        $role->syncPermissions($permissions);

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
            ->test(PayrollComponentOverridesPage::class)
            ->assertForbidden();
    }

    public function test_can_add_override(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(PayrollComponentOverridesPage::class)
            ->set('companyId', $company->id)
            ->call('openAddModal')
            ->set('addUserId', $employee->id)
            ->set('addComponent', PayrollComponentEnum::TUNJANGAN_JABATAN->value)
            ->set('addAmount', '750000')
            ->call('saveAdd');

        $override = PayrollComponentOverride::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(750000.0, $override->amount);
        $this->assertTrue($override->is_active);
    }

    public function test_cannot_add_duplicate_override_for_same_component(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        PayrollComponentOverride::create([
            'user_id' => $employee->id,
            'component' => PayrollComponentEnum::INSENTIF->value,
            'amount' => 100000,
            'is_active' => true,
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollComponentOverridesPage::class)
            ->set('companyId', $company->id)
            ->call('openAddModal')
            ->set('addUserId', $employee->id)
            ->set('addComponent', PayrollComponentEnum::INSENTIF->value)
            ->set('addAmount', '200000')
            ->call('saveAdd')
            ->assertStatus(409);
    }

    public function test_toggle_active_flips_status(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $override = PayrollComponentOverride::create([
            'user_id' => $employee->id,
            'component' => PayrollComponentEnum::LEMBUR->value,
            'amount' => 50000,
            'is_active' => true,
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollComponentOverridesPage::class)
            ->set('companyId', $company->id)
            ->call('toggleActive', $override->id);

        $this->assertFalse($override->fresh()->is_active);
    }

    public function test_edit_without_permission_is_forbidden(): void
    {
        $finance = $this->actingAsFinance(canEdit: false);
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(PayrollComponentOverridesPage::class)
            ->set('companyId', $company->id)
            ->call('openAddModal')
            ->assertForbidden();
    }
}
