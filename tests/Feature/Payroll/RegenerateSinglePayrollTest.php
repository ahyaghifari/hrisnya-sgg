<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegenerateSinglePayrollTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Regenerate Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, 'Update:Payroll']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompanyPeriodPayroll(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $otherEmployee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'processed_at' => now()]);
        $payroll = Payroll::create(['user_id' => $employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 1000000, 'net_salary' => 1000000]);
        PayrollItem::create(['payroll_id' => $payroll->id, 'name' => 'Old Item', 'type' => 'earning', 'amount' => 1000000]);
        $otherPayroll = Payroll::create(['user_id' => $otherEmployee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 2000000, 'net_salary' => 2000000]);

        return [$period, $payroll, $otherPayroll];
    }

    public function test_service_replaces_only_the_target_payroll(): void
    {
        [$period, $payroll, $otherPayroll] = $this->makeCompanyPeriodPayroll();
        $actor = User::factory()->create();

        $newPayroll = app(PayrollService::class)->regenerateSinglePayroll($payroll, $actor->id);

        $this->assertDatabaseMissing('payrolls', ['id' => $payroll->id]);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
        $this->assertDatabaseHas('payrolls', ['id' => $newPayroll->id, 'user_id' => $payroll->user_id, 'payroll_period_id' => $period->id]);
        // Payroll karyawan lain di periode yang sama tidak tersentuh.
        $this->assertDatabaseHas('payrolls', ['id' => $otherPayroll->id, 'gross_salary' => 2000000]);
        $this->assertSame($actor->id, $period->fresh()->generated_by);
        $this->assertSame($actor->id, $newPayroll->fresh()->generated_by);
        $this->assertNotNull($newPayroll->fresh()->generated_at);
        // Payroll karyawan lain tidak ikut ke-stamp oleh regenerate satu orang ini.
        $this->assertNull($otherPayroll->fresh()->generated_by);
    }

    public function test_paid_payroll_cannot_be_regenerated(): void
    {
        [, $payroll] = $this->makeCompanyPeriodPayroll();
        $payroll->update(['is_paid' => true]);

        $this->expectException(\RuntimeException::class);
        app(PayrollService::class)->regenerateSinglePayroll($payroll);
    }

    public function test_livewire_button_forbidden_for_paid_payroll(): void
    {
        $finance = $this->actingAsFinance();
        [, $payroll] = $this->makeCompanyPeriodPayroll();
        $payroll->update(['is_paid' => true]);

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('regeneratePayroll')
            ->assertForbidden();
    }

    public function test_livewire_button_regenerates_and_redirects(): void
    {
        $finance = $this->actingAsFinance();
        [, $payroll] = $this->makeCompanyPeriodPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('regeneratePayroll')
            ->assertRedirect();

        $this->assertDatabaseMissing('payrolls', ['id' => $payroll->id]);
    }
}
