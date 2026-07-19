<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\GajiPokokCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `Pangkats.start_kelas_id`/`max_kelas_id` (FK ke m_kelas, dulunya integer level lepas
 * `start_kelas_level`/`max_kelas_level`) — lihat migrasi 2026_07_19_*_replace_kelas_level_...
 */
class GajiPokokCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_additive_increase_based_on_kelas_range_via_relation(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '8', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        Kelas::create(['name' => 'Kelas II', 'company_id' => $company->id, 'level' => 2]);
        $kelas3 = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        $kelas5 = Kelas::create(['name' => 'Kelas V', 'company_id' => $company->id, 'level' => 5]);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas5->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas3->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        // kelas3.level(3) - startKelas.level(1) = 2 langkah, additive: 3.000.000 * (1 + 0.08*2)
        $this->assertSame(3_480_000.0, $context->grossSalary);
    }

    public function test_skips_when_pangkat_has_no_start_kelas_mapped(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $kelas = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]); // start_kelas_id/max_kelas_id kosong
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
