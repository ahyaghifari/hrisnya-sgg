<?php

namespace Tests\Feature\Payroll;

use App\Filament\Resources\Payroll\Payrolls\Pages\ListPayrolls;
use App\Models\AuditLog;
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
 * "Batalkan Pembayaran" per-payroll individu (beda dari PayrollPeriodUnmarkAsPaidTest yang
 * membatalkan SEMUA payroll di 1 periode sekaligus) - dipakai buat koreksi 1 karyawan yang
 * salah tandai dibayar, tercatat di audit_logs (module=payroll_payment).
 */
class PayrollCancelPaidTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        foreach (['ViewAny:Payroll', 'View:Payroll', 'Update:Payroll'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'Payroll Admin Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:Payroll', 'View:Payroll', 'Update:Payroll']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_cancel_paid_sets_unpaid_and_records_audit_log(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'processed_at' => now()]);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Karyawan']);
        $payroll = Payroll::create(['user_id' => $employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'is_paid' => true]);

        Livewire::actingAs($admin)
            ->test(ListPayrolls::class)
            ->callTableAction('cancelPaid', $payroll);

        $this->assertFalse((bool) $payroll->fresh()->is_paid);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'payroll_payment',
            'key' => 'payroll:' . $payroll->id,
            'user_id' => $admin->id,
            'old_value' => 'Sudah Dibayar',
            'new_value' => 'Dibatalkan',
        ]);
    }

    public function test_cancel_paid_action_hidden_when_unpaid(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'processed_at' => now()]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $payroll = Payroll::create(['user_id' => $employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'is_paid' => false]);

        Livewire::actingAs($admin)
            ->test(ListPayrolls::class)
            ->assertTableActionHidden('cancelPaid', $payroll);
    }

    public function test_mark_as_paid_records_audit_log(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'processed_at' => now()]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $payroll = Payroll::create(['user_id' => $employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'is_paid' => false]);

        Livewire::actingAs($admin)
            ->test(ListPayrolls::class)
            ->callTableAction('markAsPaid', $payroll);

        $this->assertTrue((bool) $payroll->fresh()->is_paid);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'payroll_payment',
            'key' => 'payroll:' . $payroll->id,
            'old_value' => 'Belum Dibayar',
            'new_value' => 'Sudah Dibayar',
        ]);
    }
}
