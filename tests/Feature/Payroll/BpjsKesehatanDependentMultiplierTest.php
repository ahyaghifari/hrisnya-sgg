<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\Pangkats;
use App\Models\Payroll\BpjsDependent;
use App\Models\Payroll\PangkatBpjsBasis;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\BpjsKesehatanCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BpjsKesehatanDependentMultiplierTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(Companies $company, ?int $dependentCount, bool $dependentActive = true): User
    {
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        PangkatBpjsBasis::create(['pangkat_id' => $pangkat->id, 'bpjs_basis' => 'ump']);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]);

        if ($dependentCount !== null) {
            BpjsDependent::create([
                'user_id' => $user->id,
                'count' => $dependentCount,
                'is_active' => $dependentActive,
            ]);
        }

        return $user;
    }

    public function test_amount_multiplied_by_one_plus_dependent_count(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'ump', 'module' => 'payroll', 'type' => 'decimal', 'value' => '3000000', 'label' => 'UMP']);
        Setting::create(['company_id' => null, 'key' => 'bpjs_kesehatan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '1', 'label' => 'Rate BPJS Kesehatan']);

        $user = $this->makeUser($company, dependentCount: 2);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new BpjsKesehatanCalculator())->calculate($user, $payroll, $period, $context);

        // basis 3.000.000 * rate 1% * (1 + 2 tanggungan) = 90.000
        $this->assertSame(90_000.0, $context->totalDeductions);
    }

    public function test_no_dependent_row_behaves_like_multiplier_one(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'ump', 'module' => 'payroll', 'type' => 'decimal', 'value' => '3000000', 'label' => 'UMP']);
        Setting::create(['company_id' => null, 'key' => 'bpjs_kesehatan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '1', 'label' => 'Rate BPJS Kesehatan']);

        $user = $this->makeUser($company, dependentCount: null);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new BpjsKesehatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(30_000.0, $context->totalDeductions);
    }

    public function test_inactive_dependent_row_ignored(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'ump', 'module' => 'payroll', 'type' => 'decimal', 'value' => '3000000', 'label' => 'UMP']);
        Setting::create(['company_id' => null, 'key' => 'bpjs_kesehatan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '1', 'label' => 'Rate BPJS Kesehatan']);

        $user = $this->makeUser($company, dependentCount: 3, dependentActive: false);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new BpjsKesehatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(30_000.0, $context->totalDeductions);
    }

    public function test_zero_when_joining_date_falls_mid_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'ump', 'module' => 'payroll', 'type' => 'decimal', 'value' => '3000000', 'label' => 'UMP']);
        Setting::create(['company_id' => null, 'key' => 'bpjs_kesehatan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '1', 'label' => 'Rate BPJS Kesehatan']);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        PangkatBpjsBasis::create(['pangkat_id' => $pangkat->id, 'bpjs_basis' => 'ump']);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'joining_date' => '2026-07-27']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new BpjsKesehatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
    }
}
