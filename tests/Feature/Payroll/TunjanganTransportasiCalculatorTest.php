<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\TransportAllowance;
use App\Models\Setting;
use App\Models\User;
use App\Models\Employee\EmployeeContract;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\PayrollContext;
use App\Services\Payroll\Calculators\TunjanganTransportasiCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lookup langsung per kelas (bukan pangkat x kelas seperti Tunjangan Jabatan). */
class TunjanganTransportasiCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_from_kelas(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        TransportAllowance::create(['kelas_id' => $kelas->id, 'amount' => 300000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'kelas_id' => $kelas->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganTransportasiCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(300_000.0, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'tunjangan_transportasi', 'amount' => 300000]);
    }

    public function test_prorates_when_joining_date_falls_mid_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        TransportAllowance::create(['kelas_id' => $kelas->id, 'amount' => 300000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'kelas_id' => $kelas->id, 'joining_date' => '2026-07-27']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganTransportasiCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(round(300_000 * 5 / 31, 2), $context->grossSalary);
    }

    public function test_no_kelas_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id]); // tanpa kelas

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganTransportasiCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }

    public function test_no_matching_allowance_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        // Sengaja tidak bikin TransportAllowance.

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'kelas_id' => $kelas->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganTransportasiCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }

    /** Transisi Orientasi->Kontrak mode 'tiered' (instruksi user 2026-09-15) - sama pola dengan
     * Tunjangan Jabatan, Orientasi Rp0 sebelum TTD. */
    public function test_tiered_transition_prorates_from_ttd_date_when_signed_day_6_to_20(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'kontrak_prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'tiered', 'label' => 'Metode Gaji Bulan TTD Kontrak']);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        TransportAllowance::create(['kelas_id' => $kelas->id, 'amount' => 300000]);

        $kontrak = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id, 'category' => 'kontrak']);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'kelas_id' => $kelas->id, 'employee_type_id' => $kontrak->id, 'joining_date' => '2025-01-01']);
        EmployeeContract::create(['employee_id' => $user->employee->id, 'type' => 'kontrak', 'company_id' => $company->id, 'employee_type_id' => $kontrak->id, 'start_date' => '2026-07-10', 'end_date' => '2027-07-09']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganTransportasiCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        // 10-31 Juli = 19 hari kerja / 31 hari kalender * 300.000 - porsi sebelum TTD Rp0.
        $this->assertSame(round(300_000 * 19 / 31, 2), $context->grossSalary);
    }
}
