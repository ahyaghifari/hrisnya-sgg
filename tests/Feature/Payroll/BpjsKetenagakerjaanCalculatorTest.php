<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\Pangkats;
use App\Models\Payroll\PangkatBpjsBasis;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\BpjsKetenagakerjaanCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AbstractBpjsCalculator: basis dari pangkat.bpjsBasis ('ump' atau 'declared_value'), rate SELALU
 * global (bukan company-scoped) via GlobalPayrollSettingEnum::BPJS_KETENAGAKERJAAN_RATE.
 */
class BpjsKetenagakerjaanCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_from_ump_basis_and_global_rate(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'ump', 'module' => 'payroll', 'type' => 'decimal', 'value' => '3000000', 'label' => 'UMP']);
        Setting::create(['company_id' => null, 'key' => 'bpjs_ketenagakerjaan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '2', 'label' => 'Rate']);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        PangkatBpjsBasis::create(['pangkat_id' => $pangkat->id, 'bpjs_basis' => 'ump']);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new BpjsKetenagakerjaanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        // 3.000.000 * 2% = 60.000
        $this->assertSame(60_000.0, $context->totalDeductions);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'bpjs_ketenagakerjaan', 'amount' => 60000]);
    }

    public function test_amount_from_declared_value_basis(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'bpjs_declared_value', 'module' => 'payroll', 'type' => 'decimal', 'value' => '5000000', 'label' => 'x']);
        Setting::create(['company_id' => null, 'key' => 'bpjs_ketenagakerjaan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '2', 'label' => 'Rate']);

        $pangkat = Pangkats::create(['name' => 'Direktur', 'company_id' => $company->id]);
        PangkatBpjsBasis::create(['pangkat_id' => $pangkat->id, 'bpjs_basis' => 'declared_value']);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new BpjsKetenagakerjaanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        // 5.000.000 * 2% = 100.000
        $this->assertSame(100_000.0, $context->totalDeductions);
    }

    public function test_no_bpjs_basis_mapping_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => null, 'key' => 'bpjs_ketenagakerjaan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '2', 'label' => 'Rate']);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        // Sengaja tidak bikin PangkatBpjsBasis.

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new BpjsKetenagakerjaanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }
}
