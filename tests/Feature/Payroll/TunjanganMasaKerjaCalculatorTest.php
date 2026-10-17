<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\TenureAllowance;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserContract;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\PayrollContext;
use App\Services\Payroll\Calculators\TunjanganMasaKerjaCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bracket dari masa kerja (tahun, dihitung dari joining_date ke start_date periode). */
class TunjanganMasaKerjaCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_from_matching_bracket(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 0, 'max_years' => 4, 'amount' => 100000]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 5, 'max_years' => 9, 'amount' => 250000]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 10, 'max_years' => null, 'amount' => 500000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'joining_date' => '2019-07-01']); // 7 tahun per Juli 2026

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganMasaKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(250_000.0, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'tunjangan_masa_kerja', 'amount' => 250000, 'note' => 'Masa kerja 7 tahun']);
    }

    public function test_open_ended_top_bracket_matches_beyond_min_years(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 0, 'max_years' => 4, 'amount' => 100000]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 10, 'max_years' => null, 'amount' => 500000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'joining_date' => '2010-07-01']); // 16 tahun

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganMasaKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(500_000.0, $context->grossSalary);
    }

    public function test_prorates_when_joining_date_falls_mid_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 0, 'max_years' => 4, 'amount' => 100000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        // Baru bergabung 27 Juli - bracket 0 tahun DAN prorate bulan pertama dua-duanya kena.
        UserProfiles::create(['user_id' => $user->id, 'joining_date' => '2026-07-27']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganMasaKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(round(100_000 * 5 / 31, 2), $context->grossSalary);
    }

    public function test_no_joining_date_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 0, 'max_years' => null, 'amount' => 100000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id]); // tanpa joining_date

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganMasaKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }

    public function test_no_matching_bracket_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 5, 'max_years' => 9, 'amount' => 250000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'joining_date' => '2025-07-01']); // 1 tahun, di bawah bracket manapun

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganMasaKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }

    /** Transisi Orientasi->Kontrak mode 'tiered' (instruksi user 2026-09-15) - sama pola dengan
     * Tunjangan Jabatan/Transportasi, Orientasi Rp0 sebelum TTD. */
    public function test_tiered_transition_prorates_from_ttd_date_when_signed_day_6_to_20(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'kontrak_prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'tiered', 'label' => 'Metode Gaji Bulan TTD Kontrak']);
        TenureAllowance::create(['company_id' => $company->id, 'min_years' => 0, 'max_years' => 4, 'amount' => 100000]);

        $kontrak = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id, 'category' => 'kontrak']);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $kontrak->id, 'joining_date' => '2026-01-01']); // 0 tahun, tidak ikut prorate joining_date
        UserContract::create(['user_id' => $user->id, 'company_id' => $company->id, 'employee_type_id' => $kontrak->id, 'contract_start_date' => '2026-07-10', 'contract_end_date' => '2027-07-09']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganMasaKerjaCalculator())->calculate($user, $payroll, $period, $context);

        // 10-31 Juli = 19 hari kerja / 31 hari kalender * 100.000 - porsi sebelum TTD Rp0.
        $this->assertSame(round(100_000 * 19 / 31, 2), $context->grossSalary);
    }
}
