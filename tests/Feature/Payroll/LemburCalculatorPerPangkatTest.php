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
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\LemburCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Metode "per_pangkat" (setting `lembur_metode`) — 1 rate/jam per pangkat, pola sama
 * Tunjangan Transportasi. Menggantikan rencana awal rumus TL/TM Holding (analisis-payroll-4
 * §3.6) yang dibatalkan user demi kesederhanaan struktur data.
 */
class LemburCalculatorPerPangkatTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(float $hours = 3.0): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        Setting::create(['company_id' => $company->id, 'key' => 'lembur_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'per_pangkat', 'label' => 'Metode Lembur']);

        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $request->id, 'date' => '2026-07-10', 'total_hours' => $hours]);

        return compact('company', 'pangkat', 'user', 'period', 'payroll');
    }

    public function test_calculates_amount_from_pangkat_rate_times_hours(): void
    {
        $s = $this->makeSetup(hours: 3.0);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 20000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $this->assertSame(60000.0, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $s['payroll']->id, 'source_type' => 'lembur', 'amount' => 60000]);
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

    public function test_skips_hours_below_minimum_jam_setting(): void
    {
        $s = $this->makeSetup(hours: 1.5);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 20000]);
        Setting::create(['company_id' => $s['company']->id, 'key' => 'lembur_minimum_jam', 'module' => 'payroll', 'type' => 'integer', 'value' => '2', 'label' => 'Minimum Jam Lembur']);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $s['payroll']->id]);
    }

    public function test_shift_metode_is_unaffected_default(): void
    {
        $s = $this->makeSetup(hours: 3.0);
        Setting::where('company_id', $s['company']->id)->where('key', 'lembur_metode')->update(['value' => 'shift']);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 20000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['user'], $s['payroll'], $s['period'], $context);

        // Metode shift butuh ShiftOvertimeCosts (tidak di-setup di sini) -> tidak ada PayrollItem
        // dari rate per pangkat manapun, membuktikan branch per_pangkat tidak ikut jalan diam-diam.
        $this->assertSame(0.0, $context->grossSalary);
    }
}
