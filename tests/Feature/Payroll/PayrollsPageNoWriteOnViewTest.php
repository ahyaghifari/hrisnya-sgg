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

/** Verifikasi empiris: buka /keuangan/payroll (list) TIDAK menulis apa pun ke kolom nominal
 * terenkripsi - dicek RAW lewat DB::table (bukan lewat model/cast), sebelum & sesudah. */
class PayrollsPageNoWriteOnViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_list_page_does_not_change_raw_encrypted_columns(): void
    {
        Permission::firstOrCreate(['name' => 'ViewAny:Payroll', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Payroll No Write Test', 'guard_name' => 'web']);
        $role->givePermissionTo('ViewAny:Payroll');
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $company = Companies::create(['code' => 'VNW', 'name' => 'Verify No Write', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $payroll = Payroll::create(['user_id' => $employee->id, 'employee_id' => $employee->employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'total_deductions' => 500000]);

        $before = DB::table('payrolls')->where('id', $payroll->id)->first(['gross_salary', 'net_salary', 'total_deductions']);

        Livewire::actingAs($admin)
            ->test(PayrollsPage::class)
            ->set('companyId', $company->id)
            ->set('periodId', $period->id)
            ->assertOk();

        $after = DB::table('payrolls')->where('id', $payroll->id)->first(['gross_salary', 'net_salary', 'total_deductions']);

        $this->assertSame($before->gross_salary, $after->gross_salary, 'gross_salary raw berubah cuma gara-gara buka halaman!');
        $this->assertSame($before->net_salary, $after->net_salary, 'net_salary raw berubah cuma gara-gara buka halaman!');
        $this->assertSame($before->total_deductions, $after->total_deductions, 'total_deductions raw berubah cuma gara-gara buka halaman!');
        $this->assertFalse(is_numeric(trim((string) $after->gross_salary)), 'gross_salary jadi plaintext (angka) setelah halaman dibuka!');
    }
}
