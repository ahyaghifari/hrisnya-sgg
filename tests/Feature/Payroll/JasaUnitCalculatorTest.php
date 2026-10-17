<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\JasaUnitDetail;
use App\Models\Payroll\JasaUnitType;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\Calculators\JasaUnitCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiap baris jasa_unit_details jadi PayrollItem TERPISAH dengan nama jasanya sendiri (bukan
 * digabung jadi 1 baris "Jasa Unit") — lihat planning/2026-08-04-payroll-jasa-unit-per-divisi.md.
 */
class JasaUnitCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_separate_payroll_item_per_jasa_and_sums_earning(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $ambulan = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan']);
        $infus = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Paket Infus']);

        JasaUnitDetail::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'jasa_unit_type_id' => $ambulan->id, 'amount' => 150_000]);
        JasaUnitDetail::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'jasa_unit_type_id' => $infus->id, 'amount' => 50_000]);

        $context = new PayrollContext();
        (new JasaUnitCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(200_000.0, $context->grossSalary);

        $items = PayrollItem::where('payroll_id', $payroll->id)->where('source_type', PayrollComponentEnum::JASA_UNIT->value)->get();
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['Jasa Ambulan', 'Jasa Paket Infus'], $items->pluck('name')->all());
        $this->assertEqualsCanonicalizing([150_000.0, 50_000.0], $items->pluck('amount')->map(fn ($a) => (float) $a)->all());
    }

    public function test_skips_zero_amount_rows(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $jasa = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Perujuk Rawat']);
        JasaUnitDetail::create(['payroll_period_id' => $period->id, 'user_id' => $user->id, 'jasa_unit_type_id' => $jasa->id, 'amount' => 0]);

        $context = new PayrollContext();
        (new JasaUnitCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertSame(0, PayrollItem::where('payroll_id', $payroll->id)->count());
    }

    public function test_another_users_details_are_not_included(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherUser = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $jasa = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan']);
        JasaUnitDetail::create(['payroll_period_id' => $period->id, 'user_id' => $otherUser->id, 'jasa_unit_type_id' => $jasa->id, 'amount' => 100_000]);

        $context = new PayrollContext();
        (new JasaUnitCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
