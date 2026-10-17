<?php

namespace Tests\Feature\Payroll;

use App\Livewire\Keuangan\PayrollsPage;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Baris payroll yang nominalnya gagal didekripsi (data lama masih plaintext, atau key beda -
 * lihat App\Casts\PayrollEncrypted) TIDAK BOLEH bikin seluruh halaman /keuangan/payroll crash
 * (dulu ViewException, laporan user 2026-08-20) - baris itu ditandai `decryptFailed` dan tampil
 * "Gagal dekripsi nominal" saja, baris lain tetap normal.
 */
class PayrollsPageDecryptFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:Payroll', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Payroll Admin Test', 'guard_name' => 'web']);
        $role->givePermissionTo('ViewAny:Payroll');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_broken_row_does_not_crash_page_and_is_flagged(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $goodEmployee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Aman']);
        $goodPayroll = Payroll::create(['employee_id' => $goodEmployee->employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'total_deductions' => 500000]);

        $brokenEmployee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Rusak']);
        $brokenPayroll = Payroll::create(['employee_id' => $brokenEmployee->employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'total_deductions' => 500000]);
        // Simulasi data lama yang masih plaintext (belum ter-reencrypt) - tulis langsung lewat
        // query builder, melewati cast PayrollEncrypted sama sekali.
        DB::table('payrolls')->where('id', $brokenPayroll->id)->update(['gross_salary' => '5000000']);

        $component = Livewire::actingAs($admin)
            ->test(PayrollsPage::class)
            ->set('companyId', $company->id)
            ->set('periodId', $period->id);

        $component->assertOk();
        $component->assertSee('Budi Aman');
        $component->assertSee('Budi Rusak');
        $component->assertSee('Gagal dekripsi nominal');

        $payrolls = $component->get('payrolls');
        $this->assertFalse($payrolls->firstWhere('id', $goodPayroll->id)->decryptFailed);
        $this->assertTrue($payrolls->firstWhere('id', $brokenPayroll->id)->decryptFailed);
    }
}
