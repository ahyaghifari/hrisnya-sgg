<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\DataRekeningPage;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DataRekeningPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(bool $canEdit = true): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_DATA_REKENING->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::EDIT_DATA_REKENING->value, 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'Finance Rekening Test', 'guard_name' => 'web']);
        $permissions = [CustomPermissionEnum::LIHAT_DATA_REKENING->value];
        if ($canEdit) {
            $permissions[] = CustomPermissionEnum::EDIT_DATA_REKENING->value;
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
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DataRekeningPage::class)->assertForbidden();
    }

    public function test_lists_active_employees_with_bank_data(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $finance->update(['company_id' => $company->id]);

        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso', 'is_active' => true]);
        UserProfiles::create([
            'user_id' => $employee->id,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Budi Santoso',
        ]);

        $this->actingAs($finance);

        Livewire::test(DataRekeningPage::class)
            ->assertSee('Budi Santoso')
            ->assertSee('BCA')
            ->assertSee('1234567890');
    }

    public function test_edit_button_hidden_without_edit_permission(): void
    {
        $finance = $this->actingAsFinance(canEdit: false);
        $company = $this->makeCompany();
        $finance->update(['company_id' => $company->id]);

        User::factory()->create(['company_id' => $company->id, 'name' => 'Ani', 'is_active' => true]);

        $this->actingAs($finance);

        Livewire::test(DataRekeningPage::class)
            ->assertDontSee('Edit');
    }

    public function test_finance_can_save_bank_data(): void
    {
        $finance = $this->actingAsFinance();
        $company = $this->makeCompany();
        $finance->update(['company_id' => $company->id]);

        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        $this->actingAs($finance);

        Livewire::test(DataRekeningPage::class)
            ->call('openEditModal', $employee->id)
            ->set('editBankName', 'Mandiri')
            ->set('editBankAccountNumber', '9876543210')
            ->set('editBankAccountName', $employee->name)
            ->call('save');

        $this->assertSame('Mandiri', $employee->fresh()->profile->bank_name);
        $this->assertSame('9876543210', $employee->fresh()->profile->bank_account_number);
    }
}
