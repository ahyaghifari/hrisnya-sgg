<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PositionAllowance;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\PayrollContext;
use App\Services\Payroll\Calculators\TunjanganJabatanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lookup langsung matriks pangkat x kelas - tidak ketemu baris = skip (0 baris), bukan error. */
class TunjanganJabatanCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_from_pangkat_kelas_matrix(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        PositionAllowance::create(['pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'amount' => 750000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(750_000.0, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'tunjangan_jabatan', 'amount' => 750000]);
    }

    public function test_no_matrix_row_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        // Sengaja tidak bikin PositionAllowance.

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }

    public function test_missing_pangkat_or_kelas_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id]); // tanpa pangkat/kelas

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
