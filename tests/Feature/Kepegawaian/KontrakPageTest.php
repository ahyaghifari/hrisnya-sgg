<?php

namespace Tests\Feature\Kepegawaian;

use App\Livewire\Kepegawaian\KontrakPage;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\User;
use App\Models\User\UserContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Padanan portal dari admin UserContractResource - lihat + Edit via modal (Tambah/Hapus sengaja
 * tidak ada, lihat docblock KontrakPage). */
class KontrakPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingWithPermission(array $extra = []): User
    {
        $names = ['ViewAny:UserContract', ...$extra];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'Kontrak Portal Test ' . implode('-', $extra), 'guard_name' => 'web']);
        $role->givePermissionTo($names);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(KontrakPage::class)
            ->assertForbidden();
    }

    public function test_lists_contracts_filtered_by_status(): void
    {
        $actor = $this->actingWithPermission();

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $active = UserContract::create([
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'employee_type_id' => $employeeType->id,
            'contract_start_date' => now()->subMonth(),
            'contract_end_date' => now()->addMonths(6),
        ]);
        $expired = UserContract::create([
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'employee_type_id' => $employeeType->id,
            'contract_start_date' => now()->subYears(2),
            'contract_end_date' => now()->subYear(),
        ]);

        $component = Livewire::actingAs($actor)
            ->test(KontrakPage::class)
            ->set('companyId', $company->id);

        $this->assertCount(2, $component->get('contracts'));

        $component->call('setStatus', 'aktif');
        $ids = $component->get('contracts')->pluck('id');
        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($expired->id));

        $component->call('setStatus', 'berakhir');
        $ids = $component->get('contracts')->pluck('id');
        $this->assertFalse($ids->contains($active->id));
        $this->assertTrue($ids->contains($expired->id));
    }

    public function test_can_edit_contract_via_modal(): void
    {
        $actor = $this->actingWithPermission(['Update:UserContract']);

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $contract = UserContract::create([
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'employee_type_id' => $employeeType->id,
            'contract_start_date' => now()->subMonth(),
            'contract_end_date' => now()->addMonths(6),
        ]);

        $newEndDate = now()->addMonths(12)->toDateString();

        Livewire::actingAs($actor)
            ->test(KontrakPage::class)
            ->set('companyId', $company->id)
            ->call('openEditModal', $contract->id)
            ->set('formEndDate', $newEndDate)
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertSame($newEndDate, $contract->fresh()->contract_end_date->toDateString());
    }

    public function test_user_without_update_permission_cannot_edit_contract(): void
    {
        $actor = $this->actingWithPermission();

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $contract = UserContract::create([
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'employee_type_id' => $employeeType->id,
            'contract_start_date' => now()->subMonth(),
            'contract_end_date' => now()->addMonths(6),
        ]);

        Livewire::actingAs($actor)
            ->test(KontrakPage::class)
            ->call('openEditModal', $contract->id)
            ->assertForbidden();
    }
}
