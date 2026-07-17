<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * periodHasImportedIncentives() adalah guard dipakai UI (PayrollPeriodsTable) SEBELUM
 * regenerate menghapus payroll unpaid — insentif "import" (RSU) ditempel manual sesudah
 * generate, jadi kalau dihapus tidak ada cara otomatis menulis ulang, lihat
 * PayrollService::periodHasImportedIncentives() docblock.
 */
class PayrollServiceRegenerateGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makePeriodAndPayroll(bool $isPaid = false): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id, 'is_paid' => $isPaid]);

        return [$period, $payroll];
    }

    public function test_true_when_period_has_imported_incentive_item(): void
    {
        [$period, $payroll] = $this->makePeriodAndPayroll();
        PayrollItem::create(['payroll_id' => $payroll->id, 'name' => 'Insentif', 'type' => 'earning', 'amount' => 500000, 'source_type' => 'insentif']);

        $this->assertTrue((new PayrollService())->periodHasImportedIncentives($period));
    }

    public function test_false_when_no_incentive_items(): void
    {
        [$period] = $this->makePeriodAndPayroll();

        $this->assertFalse((new PayrollService())->periodHasImportedIncentives($period));
    }

    public function test_false_when_incentive_item_only_on_already_paid_payroll(): void
    {
        [$period, $payroll] = $this->makePeriodAndPayroll(isPaid: true);
        PayrollItem::create(['payroll_id' => $payroll->id, 'name' => 'Insentif', 'type' => 'earning', 'amount' => 500000, 'source_type' => 'insentif']);

        // Payroll is_paid=true tidak akan disentuh regeneratePayroll() sama sekali — bukan
        // risiko kehilangan data, jadi tidak perlu diblokir.
        $this->assertFalse((new PayrollService())->periodHasImportedIncentives($period));
    }
}
