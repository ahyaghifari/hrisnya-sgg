<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\Calculators\CutiCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cuti TIDAK ADA POTONGAN (dikonfirmasi user) - no-op sepenuhnya. */
class CutiCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_never_creates_payroll_item_or_touches_context(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new CutiCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertSame(0.0, $context->totalDeductions);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }
}
