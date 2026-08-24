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

/** Padanan portal read-only dari admin UserContractResource. */
class KontrakPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingWithPermission(): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:UserContract', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kontrak Portal Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:UserContract']);

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
}
