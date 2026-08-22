<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Koperasi anggota aktif (user_koperasi) dibaca LANGSUNG oleh KoperasiCalculator tiap Generate
 * Payroll — TIDAK lewat payroll_manual_lines (2026-08-22, beda dari Bon/Insentif import).
 */
class KoperasiSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_is_deducted(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KOPERASI->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KOPERASI->value, 'employee_type_id' => $employeeType->id]);
        UserKoperasi::create(['user_id' => $user->id, 'amount' => 150000, 'is_active' => true]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);

        $this->assertDatabaseMissing('payroll_manual_lines', ['component' => 'koperasi']);

        $payroll = \App\Models\Payroll\Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();
        $item = $payroll->items()->where('source_type', 'koperasi')->first();

        $this->assertNotNull($item);
        $this->assertSame(150000.0, (float) $item->amount);
    }

    public function test_inactive_member_is_not_deducted(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KOPERASI->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KOPERASI->value, 'employee_type_id' => $employeeType->id]);
        UserKoperasi::create(['user_id' => $user->id, 'amount' => 150000, 'is_active' => false]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);

        $payroll = \App\Models\Payroll\Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        // Koperasi tetap komponen aktif di company ini - baris Rp0 tetap muncul di slip (perilaku
        // umum semua komponen aktif, lihat PayrollService::runComponent()), cuma tidak ada
        // potongan nyata.
        $item = $payroll->items()->where('source_type', 'koperasi')->first();
        $this->assertNotNull($item);
        $this->assertSame(0.0, (float) $item->amount);
    }
}
