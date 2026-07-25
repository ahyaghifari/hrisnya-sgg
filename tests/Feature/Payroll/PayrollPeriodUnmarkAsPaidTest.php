<?php

namespace Tests\Feature\Payroll;

use App\Filament\Resources\Payroll\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Batalkan Status Dibayar" — langkah 1 dari 2 buat hapus periode yang punya payroll sudah
 * dibayar (deletePeriod di PayrollPeriodsTable ditolak selama ada is_paid=true). Aksi ini cuma
 * mengubah is_paid, tidak menghapus data payroll-nya sendiri.
 */
class PayrollPeriodUnmarkAsPaidTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        foreach (['ViewAny:PayrollPeriod', 'View:PayrollPeriod', 'Update:PayrollPeriod', 'Delete:PayrollPeriod'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'Payroll Admin Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:PayrollPeriod', 'View:PayrollPeriod', 'Update:PayrollPeriod', 'Delete:PayrollPeriod']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_unmark_as_paid_sets_all_payrolls_in_period_to_unpaid(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'processed_at' => now(),
        ]);

        $employeeA = User::factory()->create(['company_id' => $company->id]);
        $employeeB = User::factory()->create(['company_id' => $company->id]);

        $paid = Payroll::create(['user_id' => $employeeA->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'is_paid' => true]);
        $unpaid = Payroll::create(['user_id' => $employeeB->id, 'payroll_period_id' => $period->id, 'gross_salary' => 3000000, 'net_salary' => 2800000, 'is_paid' => false]);

        Livewire::actingAs($admin)
            ->test(ListPayrollPeriods::class)
            ->callTableAction('unmarkAsPaid', $period);

        $this->assertFalse((bool) $paid->fresh()->is_paid);
        $this->assertFalse((bool) $unpaid->fresh()->is_paid);
    }

    public function test_unmark_as_paid_action_hidden_when_nothing_is_paid(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'processed_at' => now(),
        ]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        Payroll::create(['user_id' => $employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 3000000, 'net_salary' => 2800000, 'is_paid' => false]);

        Livewire::actingAs($admin)
            ->test(ListPayrollPeriods::class)
            ->assertTableActionHidden('unmarkAsPaid', $period);
    }
}
