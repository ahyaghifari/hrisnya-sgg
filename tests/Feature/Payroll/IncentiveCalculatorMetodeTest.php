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
 * ("master", Holding) atau dihitung live dari selisih Tunjangan Jabatan ("auto", RSU). Metode
 * "import" (dari payroll_manual_lines) SUDAH DIHAPUS - lihat docblock IncentiveCalculator.
 */
class IncentiveCalculatorMetodeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        Incentive::create([
            'user_id' => $user->id,
            'name' => 'Insentif Juli',
            'incentive_date' => '2026-07-15',
            'amount' => 300000,
            'is_active' => true,
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

    public function test_unknown_metode_falls_back_to_master(): void
    {
        // Metode "import" sudah dihapus - value string itu (atau apa pun selain 'auto') sekarang
        // jatuh ke perilaku default 'master', bukan dispatch ke jalur lain.
        [, , $period, $payroll] = $this->makeSetup();
        Setting::create(['company_id' => $period->company_id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'import', 'label' => 'Metode Insentif']);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(300000.0, $context->grossSalary);
    }

    /**
     * Metode "master" jadi roster ala Koperasi — tanggal cuma keterangan, TIDAK dipakai buat
     * memfilter periode payroll. Baris di luar bulan periode tetap ikut selama is_active=true.
     */
    public function test_master_metode_ignores_period_date_range(): void
    {
        [, $user, $period, $payroll] = $this->makeSetup();
        Setting::create(['company_id' => $period->company_id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'master', 'label' => 'Metode Insentif']);

        // Baris tambahan dengan tanggal jauh di luar periode Juli (periode payroll di
        // makeSetup() adalah 2026-07-01 s/d 2026-07-31).
        Incentive::create(['user_id' => $user->id, 'incentive_date' => '2026-01-01', 'amount' => 100000, 'is_active' => true]);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(400000.0, $context->grossSalary);
    }

    public function test_master_metode_excludes_inactive_rows(): void
    {
        [, $user, $period, $payroll] = $this->makeSetup();
        Setting::create(['company_id' => $period->company_id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'master', 'label' => 'Metode Insentif']);

        Incentive::create(['user_id' => $user->id, 'incentive_date' => '2026-07-15', 'amount' => 999999, 'is_active' => false]);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        // Cuma baris aktif dari makeSetup() (300000) yang dihitung.
        $this->assertSame(300000.0, $context->grossSalary);
    }

    /**
     * Baris roster tidak "terkonsumsi sekali" — regenerate periode yang sama (payroll lama
     * dihapus, dihitung ulang) harus menghasilkan nominal identik, bukan 0.
     */
    public function test_master_metode_is_regenerate_safe(): void
    {
        [, , $period, $payroll] = $this->makeSetup();
        Setting::create(['company_id' => $period->company_id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'master', 'label' => 'Metode Insentif']);

        $firstContext = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $firstContext);
        $this->assertSame(300000.0, $firstContext->grossSalary);

        // Simulasikan regenerate: payroll_items lama dihapus, payroll dihitung ulang.
        $payroll->items()->delete();

        $secondContext = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll->fresh(), $period, $secondContext);

        $this->assertSame(300000.0, $secondContext->grossSalary);
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
