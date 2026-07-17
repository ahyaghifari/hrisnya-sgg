<?php

namespace Tests\Feature\Payroll;

use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\Calculators\IncentiveCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setting `insentif_metode` menentukan apakah IncentiveCalculator baca dari tabel `incentives`
 * ("master", Holding) atau skip total ("import", RSU — insentifnya ditempel manual lewat
 * IncentiveImport SETELAH generate, lihat IncentiveImportTest).
 */
class IncentiveCalculatorMetodeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        Incentive::create([
            'user_id' => $user->id,
            'name' => 'Insentif Juli',
            'incentive_date' => '2026-07-15',
            'amount' => 300000,
            'is_processed' => false,
        ]);

        return [$company, $user, $period, $payroll];
    }

    public function test_master_metode_reads_from_incentives_table(): void
    {
        [, , $period, $payroll] = $this->makeSetup();
        Setting::create(['company_id' => $period->company_id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'master', 'label' => 'Metode Insentif']);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(300000.0, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'insentif', 'amount' => 300000]);
    }

    public function test_import_metode_skips_entirely_even_if_incentives_rows_exist(): void
    {
        [, , $period, $payroll] = $this->makeSetup();
        Setting::create(['company_id' => $period->company_id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'import', 'label' => 'Metode Insentif']);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'insentif']);
        // Baris incentives tidak ikut ditandai processed — masih murni tidak tersentuh.
        $this->assertDatabaseHas('incentives', ['user_id' => $payroll->user_id, 'is_processed' => false]);
    }

    public function test_defaults_to_master_when_setting_missing(): void
    {
        [, , $period, $payroll] = $this->makeSetup();
        // Tidak ada baris Setting sama sekali untuk company ini atau global.

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(300000.0, $context->grossSalary);
    }
}
