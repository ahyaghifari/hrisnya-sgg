<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\KoperasiPage;
use App\Models\Master\Companies;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;
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
        $role = Role::firstOrCreate(['name' => 'Keuangan Koperasi Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEUANGAN->value);

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

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->call('openAddModal')
            ->set('addUserId', $employee->id)
            ->set('addAmount', '150000')
            ->call('saveAdd');

        $this->assertDatabaseHas('user_koperasi', ['user_id' => $employee->id, 'amount' => 150000, 'is_active' => true]);
    }

    public function test_bulk_edit_amount_updates_selected_members(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employeeA = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $employeeB = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $memberA = UserKoperasi::create(['user_id' => $employeeA->id, 'amount' => 100000, 'is_active' => true]);
        $memberB = UserKoperasi::create(['user_id' => $employeeB->id, 'amount' => 200000, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->set('selected', [$memberA->id, $memberB->id])
            ->call('openEditModal')
            ->set('editAmount', '300000')
            ->call('saveEdit');

        $this->assertDatabaseHas('user_koperasi', ['id' => $memberA->id, 'amount' => 300000]);
        $this->assertDatabaseHas('user_koperasi', ['id' => $memberB->id, 'amount' => 300000]);
    }

    public function test_toggle_active_flips_status(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $member = UserKoperasi::create(['user_id' => $employee->id, 'amount' => 100000, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->call('toggleActive', $member->id);

        $this->assertDatabaseHas('user_koperasi', ['id' => $member->id, 'is_active' => false]);
    }

    public function test_remove_member_deletes_row(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $member = UserKoperasi::create(['user_id' => $employee->id, 'amount' => 100000, 'is_active' => true]);

        Livewire::actingAs($finance)
            ->test(KoperasiPage::class)
            ->set('companyId', $company->id)
            ->call('removeMember', $member->id);

        $this->assertDatabaseMissing('user_koperasi', ['id' => $member->id]);
    }
}
