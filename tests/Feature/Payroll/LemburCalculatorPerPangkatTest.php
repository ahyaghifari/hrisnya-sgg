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
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $request->id, 'date' => $date, 'total_hours' => $hours]);

        return compact('company', 'pangkat', 'user', 'period', 'payroll');
    }

    public function test_calculates_amount_using_base_rate_days_and_multiplier_formula(): void
    {
        $s = $this->makeSetup(hours: 3.0); // Juli 2026 = 31 hari, 3 jam -> multiplier 5,5
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['payroll']->employee, $s['payroll'], $s['period'], $context);

        $expected = round(3_500_000 / (31 * 7) * 5.5, 2);

        $this->assertSame($expected, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $s['payroll']->id, 'source_type' => 'lembur', 'amount' => $expected]);
    }

    public function test_skips_when_pangkat_has_no_rate_mapped(): void
    {
        $s = $this->makeSetup(hours: 3.0);
        // Sengaja tidak bikin PangkatLemburRate.

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['payroll']->employee, $s['payroll'], $s['period'], $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $s['payroll']->id]);
    }

    public function test_hours_below_minimum_setting_are_rejected(): void
    {
        $s = $this->makeSetup(hours: 1.5); // di bawah default lembur_minimum_jam = 2
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['payroll']->employee, $s['payroll'], $s['period'], $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $s['payroll']->id]);
    }

    public function test_multiplier_floor_stays_2_hours_regardless_of_minimum_setting(): void
    {
        // Minimum di-set 1 -> lembur 1 jam lolos filter, tapi rumus Excel MAX(2; MIN(jam; 4))
        // tetap menghitungnya setara 2 jam (multiplier 3,5).
        $s = $this->makeSetup(hours: 1.0);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);
        Setting::create(['company_id' => $s['company']->id, 'key' => 'lembur_minimum_jam', 'module' => 'payroll', 'type' => 'integer', 'value' => '1', 'label' => 'Minimum Jam Lembur']);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['payroll']->employee, $s['payroll'], $s['period'], $context);

        $this->assertSame(round(3_500_000 / (31 * 7) * 3.5, 2), $context->grossSalary);
    }

    public function test_decimal_hours_are_not_rounded_down(): void
    {
        $s = $this->makeSetup(hours: 2.5); // multiplier (2,5 x 2) - 0,5 = 4,5
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['payroll']->employee, $s['payroll'], $s['period'], $context);

        $expected = round(3_500_000 / (31 * 7) * 4.5, 2);
        $this->assertSame($expected, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $s['payroll']->id, 'quantity' => 2.5]);
    }

    public function test_hours_above_4_capped_at_maximum_multiplier(): void
    {
        $s = $this->makeSetup(hours: 6.0);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['payroll']->employee, $s['payroll'], $s['period'], $context);

        $expected = round(3_500_000 / (31 * 7) * 7.5, 2); // clamp ke 4 jam -> multiplier 7,5
        $this->assertSame($expected, $context->grossSalary);
    }

    public function test_uses_attendance_period_window_not_literal_payroll_period_dates(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_start_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '21', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_end_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '20', 'label' => 'x']);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        PangkatLemburRate::create(['pangkat_id' => $pangkat->id, 'amount_per_hour' => 3_500_000]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-05-01', 'end_date' => '2026-05-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $beforeWindow = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $beforeWindow->id, 'date' => '2026-04-15', 'total_hours' => 3.0]); // sebelum window - JANGAN kehitung

        $inWindow = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $inWindow->id, 'date' => '2026-05-14', 'total_hours' => 3.0]); // HARUS kehitung

        $outsideWindow = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'overtime', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestOvertimeDetails::create(['request_id' => $outsideWindow->id, 'date' => '2026-05-25', 'total_hours' => 3.0]); // setelah window (window salah lama: Mei21-Jun20) - JANGAN kehitung

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        // Cuma lembur 14 Mei (di dalam window 21 Apr - 20 Mei) yang kehitung.
        $expected = round(3_500_000 / (31 * 7) * 5.5, 2);
        $this->assertSame($expected, $context->grossSalary);
    }

    public function test_minimum_hours_setting_overrides_default_2(): void
    {
        // Company set lembur_minimum_jam = 3 - lembur 2 jam DITOLAK (di bawah minimum 3),
        // padahal di atas default 2 - membuktikan setting benar-benar dibaca sebagai filter.
        $s = $this->makeSetup(hours: 2.0);
        PangkatLemburRate::create(['pangkat_id' => $s['pangkat']->id, 'amount_per_hour' => 3_500_000]);
        Setting::create(['company_id' => $s['company']->id, 'key' => 'lembur_minimum_jam', 'module' => 'payroll', 'type' => 'integer', 'value' => '3', 'label' => 'Minimum Jam Lembur']);

        $context = new PayrollContext();
        (new LemburCalculator())->calculate($s['payroll']->employee, $s['payroll'], $s['period'], $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
