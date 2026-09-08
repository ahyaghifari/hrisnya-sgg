<?php

namespace Tests\Feature\Payroll;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\Pangkats;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\LemburCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Metode Lembur "flat_per_jam": rate flat per company (settings.lembur_rate_per_jam) x jam
 * desimal sampai menit -> (rate x jam bulat) + (rate/60 x sisa menit). Batas minimum jam
 * tetap berlaku (lembur < lembur_minimum_jam ditolak, bukan di-clamp).
 */
class LemburCalculatorFlatPerJamTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: User, payroll: Payroll, period: PayrollPeriod, company: Companies} */
    private function makeSetup(float $hours, string $date = '2026-07-10'): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $request->id, 'date' => $date, 'total_hours' => $hours]);

        Setting::create(['company_id' => $company->id, 'key' => 'lembur_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'flat_per_jam', 'label' => 'Metode Lembur']);
        Setting::create(['company_id' => $company->id, 'key' => 'lembur_rate_per_jam', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'Rate Lembur per Jam']);

        return compact('user', 'payroll', 'period', 'company');
    }

    public function test_computes_rate_times_hours_plus_prorated_minutes(): void
    {
        $s = $this->makeSetup(hours: 2.5); // 2 jam 30 menit

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $expected = round(10000 * 2 + (10000 / 60) * 30, 2); // 20.000 + 5.000 = 25.000

        $this->assertSame($expected, $context->grossSalary);
        // amount di payroll_items dienkripsi at-rest — cukup pastikan barisnya dibuat.
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $s['payroll']->id, 'source_type' => 'lembur', 'quantity' => 2]);
    }

    public function test_hours_below_minimum_are_rejected(): void
    {
        $s = $this->makeSetup(hours: 1.5); // di bawah default lembur_minimum_jam = 2

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $s['payroll']->id]);
    }

    public function test_whole_hours_have_no_minute_component(): void
    {
        $s = $this->makeSetup(hours: 3.0);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $this->assertSame(30000.0, $context->grossSalary); // 10.000 x 3
    }

    public function test_skips_when_flat_rate_not_set(): void
    {
        $s = $this->makeSetup(hours: 3.0);
        Setting::where('company_id', $s['company']->id)->where('key', 'lembur_rate_per_jam')->delete();

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
