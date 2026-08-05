<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bon punya 2 metode (setting `bon_metode`, default 'simple') - Simple tetap dibatasi 1 baris
 * payroll_manual_lines per periode (seperti sebelum fitur ini), Detail boleh banyak baris rincian
 * potongan. Constraint unique DB yang dulu jadi jaminan "1 baris" sudah dilonggarkan (migration
 * 2026_08_01_160000_*) - guard sekarang murni di PayrollDetailPage::saveManualLine().
 */
class PayrollDetailPageManualLineTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::EDIT_PAYROLL_ADJUSTMENT->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Payroll Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, 'Update:Payroll', CustomPermissionEnum::EDIT_PAYROLL_ADJUSTMENT->value]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Finance biasa tanpa EDIT_PAYROLL_ADJUSTMENT — bisa lihat Detail Payroll (SISTEM_KEUANGAN +
     * Update:Payroll) tapi TIDAK boleh nambah/ubah/hapus baris penyesuaian manual. */
    private function actingAsFinanceWithoutAdjustmentPermission(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Payroll Test Terbatas', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, 'Update:Payroll']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makePayrollForBon(): Payroll
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Company TST', 'location' => 'Test', 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $employee->id, 'employee_type_id' => $employeeType->id]);

        CompanyPayrollComponent::create([
            'company_id' => $company->id,
            'component' => PayrollComponentEnum::BON->value,
            'is_active' => true,
        ]);
        PayrollComponentRecipient::create([
            'company_id' => $company->id,
            'component' => PayrollComponentEnum::BON->value,
            'employee_type_id' => $employeeType->id,
        ]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        return Payroll::create(['user_id' => $employee->id, 'payroll_period_id' => $period->id]);
    }

    public function test_bon_simple_mode_blocks_second_manual_line(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePayrollForBon();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openManualLineModal', 'bon')
            ->set('manualLineAmount', '100000')
            ->set('manualLineNote', 'Bon tanggal 5')
            ->call('saveManualLine');

        $this->assertSame(1, \App\Models\Payroll\PayrollManualLine::where('payroll_period_id', $payroll->payroll_period_id)->count());

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openManualLineModal', 'bon')
            ->set('manualLineAmount', '50000')
            ->set('manualLineNote', 'Ganti kerugian barang')
            ->call('saveManualLine')
            ->assertForbidden();

        $this->assertSame(1, \App\Models\Payroll\PayrollManualLine::where('payroll_period_id', $payroll->payroll_period_id)->count());
    }

    public function test_bon_detail_mode_allows_multiple_manual_lines(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePayrollForBon();

        Setting::create([
            'company_id' => $payroll->payrollPeriod->company_id,
            'key' => 'bon_metode',
            'module' => 'payroll',
            'type' => 'string',
            'value' => 'detail',
            'label' => 'Metode Bon',
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openManualLineModal', 'bon')
            ->set('manualLineAmount', '100000')
            ->set('manualLineNote', 'Bon tanggal 5')
            ->call('saveManualLine')
            ->assertOk();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openManualLineModal', 'bon')
            ->set('manualLineAmount', '50000')
            ->set('manualLineNote', 'Ganti kerugian barang')
            ->call('saveManualLine')
            ->assertOk();

        $this->assertSame(2, \App\Models\Payroll\PayrollManualLine::where('payroll_period_id', $payroll->payroll_period_id)
            ->where('component', PayrollComponentEnum::BON->value)
            ->count());
    }

    public function test_user_without_adjustment_permission_cannot_open_or_save_manual_line(): void
    {
        $finance = $this->actingAsFinanceWithoutAdjustmentPermission();
        $payroll = $this->makePayrollForBon();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openManualLineModal', 'bon')
            ->assertForbidden();

        $this->assertSame(0, \App\Models\Payroll\PayrollManualLine::where('payroll_period_id', $payroll->payroll_period_id)->count());
    }

    public function test_saving_manual_line_writes_audit_log_entry(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePayrollForBon();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openManualLineModal', 'bon')
            ->set('manualLineAmount', '100000')
            ->set('manualLineNote', 'Koreksi kesalahan hitung lembur')
            ->call('saveManualLine')
            ->assertOk();

        $log = AuditLog::where('module', 'payroll_adjustment')->first();

        $this->assertNotNull($log);
        $this->assertSame($finance->id, $log->user_id);
        $this->assertNull($log->old_value);
        $this->assertSame('100000', $log->new_value);
        $this->assertStringContainsString('Koreksi kesalahan hitung lembur', $log->label);
    }
}
