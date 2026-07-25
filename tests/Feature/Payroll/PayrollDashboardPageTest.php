<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\PayrollDashboardPage;
use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Dashboard Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEUANGAN->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Company TST', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(PayrollDashboardPage::class)
            ->assertForbidden();
    }

    public function test_shows_empty_state_when_no_period_exists(): void
    {
        $company = $this->makeCompany();
        $finance = $this->actingAsFinance($company->id);

        Livewire::actingAs($finance)
            ->test(PayrollDashboardPage::class)
            ->assertSee('Belum ada periode gaji');
    }

    public function test_summarizes_latest_period(): void
    {
        $company = $this->makeCompany();
        $finance = $this->actingAsFinance($company->id);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'processed_at' => now(),
        ]);

        $employeePaid = User::factory()->create(['company_id' => $company->id]);
        $employeeUnpaid = User::factory()->create(['company_id' => $company->id]);

        $paidPayroll = Payroll::create(['user_id' => $employeePaid->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'is_paid' => true]);
        Payroll::create(['user_id' => $employeeUnpaid->id, 'payroll_period_id' => $period->id, 'gross_salary' => 3000000, 'net_salary' => 2800000, 'is_paid' => false]);

        PayrollItem::create(['payroll_id' => $paidPayroll->id, 'name' => 'Gaji Pokok', 'type' => 'earning', 'amount' => 4500000, 'source_type' => 'gaji_pokok']);

        Incentive::create(['user_id' => $employeePaid->id, 'amount' => 500000, 'is_active' => true]);
        UserKoperasi::create(['user_id' => $employeePaid->id, 'amount' => 150000, 'is_active' => true]);

        $component = Livewire::actingAs($finance)
            ->test(PayrollDashboardPage::class)
            ->assertSee('7.300.000') // total net payroll (4.500.000 + 2.800.000)
            ->assertSee('50%') // 1 dari 2 payroll sudah dibayar
            ->assertSee('Gaji Pokok');

        $this->assertSame(2, $component->instance()->payrollCount);
        $this->assertSame(1, $component->instance()->unpaidCount);
        $this->assertSame(1, $component->instance()->activeIncentiveCount);
        $this->assertSame(1, $component->instance()->activeKoperasiCount);
    }
}
