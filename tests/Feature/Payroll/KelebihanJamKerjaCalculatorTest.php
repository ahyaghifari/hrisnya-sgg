<?php

namespace Tests\Feature\Payroll;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Shift\ExtraHourAllowanceRules;
use App\Models\User;
use App\Services\Payroll\Calculators\KelebihanJamKerjaCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kelebihan jam kerja = total working_minutes sebulan (jam) dikurangi minimum setting, dibulatkan
 * ke atas, dikali rate per jam - rate diambil per Jabatan dulu (fallback ke rate company kalau
 * job position tidak punya rule sendiri).
 */
class KelebihanJamKerjaCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttendance(User $user, string $date, int $workingMinutes): void
    {
        AttendanceRecords::create([
            'user_id' => $user->id,
            'nip' => $user->nip,
            'date' => $date,
            'working_minutes' => $workingMinutes,
            'status' => 'present',
        ]);
    }

    public function test_charges_excess_hours_using_company_fallback_rate(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'min_total_working_hours_per_month', 'module' => 'payroll', 'type' => 'decimal', 'value' => '160', 'label' => 'x']);
        // Fallback: aturan MILIK jabatan lain (bukan punya karyawan ini) - fallback company query
        // di calculator tidak filter allowanceable_id, jadi tetap kena walau bukan jabatan user ini.
        $otherPosition = JobPositions::create(['name' => 'Jabatan Lain', 'company_id' => $company->id, 'status' => 'approved']);
        ExtraHourAllowanceRules::create(['company_id' => $company->id, 'allowanceable_type' => JobPositions::class, 'allowanceable_id' => $otherPosition->id, 'amount_per_hour' => 20000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        // 170 jam kerja = 10.200 menit, minimum 160 jam -> kelebihan 10 jam.
        $this->makeAttendance($user, '2026-07-05', 10_200);

        $context = new PayrollContext();
        (new KelebihanJamKerjaCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(200_000.0, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'kelebihan_jam_kerja', 'quantity' => 10, 'amount' => 200000]);
    }

    public function test_job_position_specific_rate_overrides_company_fallback(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'min_total_working_hours_per_month', 'module' => 'payroll', 'type' => 'decimal', 'value' => '160', 'label' => 'x']);
        $jobPosition = JobPositions::create(['name' => 'Perawat', 'company_id' => $company->id, 'status' => 'approved']);
        $otherPosition = JobPositions::create(['name' => 'Jabatan Lain', 'company_id' => $company->id, 'status' => 'approved']);

        // Fallback company rendah, rate spesifik jabatan lebih tinggi - yang dipakai harus yang spesifik.
        ExtraHourAllowanceRules::create(['company_id' => $company->id, 'allowanceable_type' => JobPositions::class, 'allowanceable_id' => $otherPosition->id, 'amount_per_hour' => 5000]);
        ExtraHourAllowanceRules::create(['company_id' => $company->id, 'allowanceable_type' => JobPositions::class, 'allowanceable_id' => $jobPosition->id, 'amount_per_hour' => 30000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->jobPositions()->create(['job_position_id' => $jobPosition->id, 'start_date' => now()->subMonth(), 'status' => 'active']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeAttendance($user, '2026-07-05', 10_200); // 170 jam -> kelebihan 10 jam

        $context = new PayrollContext();
        (new KelebihanJamKerjaCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(300_000.0, $context->grossSalary);
    }

    public function test_no_excess_hours_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'min_total_working_hours_per_month', 'module' => 'payroll', 'type' => 'decimal', 'value' => '160', 'label' => 'x']);
        $otherPosition = JobPositions::create(['name' => 'Jabatan Lain', 'company_id' => $company->id, 'status' => 'approved']);
        ExtraHourAllowanceRules::create(['company_id' => $company->id, 'allowanceable_type' => JobPositions::class, 'allowanceable_id' => $otherPosition->id, 'amount_per_hour' => 20000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeAttendance($user, '2026-07-05', 9_000); // 150 jam, di bawah minimum 160

        $context = new PayrollContext();
        (new KelebihanJamKerjaCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }

    public function test_no_allowance_rule_skips_even_with_excess_hours(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'min_total_working_hours_per_month', 'module' => 'payroll', 'type' => 'decimal', 'value' => '160', 'label' => 'x']);
        // Sengaja tidak bikin ExtraHourAllowanceRules sama sekali.

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeAttendance($user, '2026-07-05', 10_200);

        $context = new PayrollContext();
        (new KelebihanJamKerjaCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
