<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * generated_by dicatat setiap Generate/Regenerate — dipakai di slip gaji bagian "Dibuat oleh".
 */
class PayrollPeriodGeneratedByTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_records_who_triggered_it(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $actor = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period, $actor->id);

        $this->assertSame($actor->id, $period->fresh()->generated_by);
    }

    public function test_regenerate_updates_generated_by_to_new_actor(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $firstActor = User::factory()->create(['company_id' => $company->id]);
        $secondActor = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period, $firstActor->id);
        app(PayrollService::class)->regeneratePayroll($period->fresh(), $secondActor->id);

        $this->assertSame($secondActor->id, $period->fresh()->generated_by);
    }

    public function test_regenerate_without_actor_keeps_previous_generated_by(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $firstActor = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period, $firstActor->id);
        app(PayrollService::class)->regeneratePayroll($period->fresh());

        $this->assertSame($firstActor->id, $period->fresh()->generated_by);
    }

    public function test_generate_stamps_generated_by_and_at_on_each_payroll(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $actor = User::factory()->create(['company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period, $actor->id);

        $payroll = \App\Models\Payroll\Payroll::where('user_id', $employee->id)->where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertSame($actor->id, $payroll->generated_by);
        $this->assertNotNull($payroll->generated_at);
    }
}
