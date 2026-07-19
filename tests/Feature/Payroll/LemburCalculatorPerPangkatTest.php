<?php

namespace Tests\Feature\Payroll;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\Pangkats;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PangkatLemburRate;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\LemburCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu-satunya metode Lembur di sistem ini — rumus lama Excel Holding (analisis-payroll-4
 * §3.6): base rate per pangkat (pangkat_lembur_rates.amount_per_hour, "TL"/"TM" digeneralisasi)
 * dibagi (hari dalam bulan x 7), dikali multiplier dari jam lembur (clamp 2-4 jam -> 3,5-7,5x).
 */
class LemburCalculatorPerPangkatTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(float $hours = 3.0, string $date = '2026-07-10'): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $request->id, 'date' => $date, 'total_hours' => $hours]);

        return compact('company', 'pangkat', 'user', 'period', 'payroll');
    }

    public function test_calculates_amount_using_base_rate_days_and_multiplier_formula(): void
    {
        $s = $this->makeSetup(hours: 3.0); // Juli 2026 = 31 hari, 3 jam -> multiplier 5,5
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $expected = round(3_500_000 / (31 * 7) * 5.5, 2);

        $this->assertSame($expected, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $s['payroll']->id, 'source_type' => 'lembur', 'amount' => $expected]);
    }

    public function test_skips_when_pangkat_has_no_rate_mapped(): void
    {
        $s = $this->makeSetup(hours: 3.0);
        // Sengaja tidak bikin PangkatLemburRate.

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $s['payroll']->id]);
    }

    public function test_hours_below_2_still_computed_as_minimum_2_not_skipped(): void
    {
        // Perilaku asli Excel: multiplier meng-clamp ke minimum 2 jam, BUKAN menolak lembur
        // di bawah 2 jam.
        $s = $this->makeSetup(hours: 1.5);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $expected = round(3_500_000 / (31 * 7) * 3.5, 2); // floor(1.5)=1 jam -> clamp ke 2 -> multiplier 3,5
        $this->assertSame($expected, $context->grossSalary);
    }

    public function test_hours_above_4_capped_at_maximum_multiplier(): void
    {
        $s = $this->makeSetup(hours: 6.0);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $expected = round(3_500_000 / (31 * 7) * 7.5, 2); // clamp ke 4 jam -> multiplier 7,5
        $this->assertSame($expected, $context->grossSalary);
    }
}
