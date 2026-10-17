<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Payroll\OmsetCategory;
use App\Models\Payroll\OmsetDetail;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\JasaPelayananCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Total JE company dihitung LIVE (SUM job_evaluation_score jabatan APPROVED), Total Omset
 * dijumlah dari omset_details per periode - menggantikan job_evaluation_companies &
 * service_fee_pools lama (lihat JasaPelayananCalculator).
 */
class JasaPelayananCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_ratio_of_je_times_total_omset(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $userPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'status' => 'approved', 'job_evaluation_score' => 300]);
        JobPositions::create(['company_id' => $company->id, 'name' => 'Dokter', 'planned_manpower' => 1, 'status' => 'approved', 'job_evaluation_score' => 700]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'job_position_id' => $userPosition->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $rawatInap = OmsetCategory::create(['company_id' => $company->id, 'name' => 'Rawat Inap BPJS', 'payer_type' => 'bpjs']);
        $rawatJalan = OmsetCategory::create(['company_id' => $company->id, 'name' => 'Rawat Jalan Umum', 'payer_type' => 'umum']);
        OmsetDetail::create(['payroll_period_id' => $period->id, 'omset_category_id' => $rawatInap->id, 'amount' => 70_000]);
        OmsetDetail::create(['payroll_period_id' => $period->id, 'omset_category_id' => $rawatJalan->id, 'amount' => 30_000]);

        $context = new PayrollContext();
        (new JasaPelayananCalculator())->calculate($user, $payroll, $period, $context);

        // Total JE = 300 + 700 = 1000. Pool = 70.000 x 2,5% (BPJS) + 30.000 x 7,5% (Umum)
        // = 1.750 + 2.250 = 4.000. (300 / 1000) * 4.000 = 1.200
        $this->assertSame(1_200.0, $context->grossSalary);
    }

    public function test_skips_when_no_omset_filled_for_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $userPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Perawat', 'planned_manpower' => 1, 'status' => 'approved', 'job_evaluation_score' => 300]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'job_position_id' => $userPosition->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new JasaPelayananCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
