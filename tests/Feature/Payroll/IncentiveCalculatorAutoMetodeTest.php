<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\LegacyPositionAllowance;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PositionAllowance;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\IncentiveCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Metode "auto" — Insentif dihitung live dari selisih legacy_position_allowances (lama) vs
 * position_allowances (baru) berdasarkan pangkat_id/kelas_id karyawan SAAT ITU. Dipakai RSU
 * sebagai penutup kekurangan Tunjangan Jabatan lama->baru tanpa input manual per orang.
 */
class IncentiveCalculatorAutoMetodeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $kelasA = Kelas::create(['name' => 'Kelas A', 'company_id' => $company->id, 'level' => 1]);
        $kelasB = Kelas::create(['name' => 'Kelas B', 'company_id' => $company->id, 'level' => 2]);
        $pangkatA = Pangkats::create(['name' => 'Pangkat A', 'company_id' => $company->id]);

        Setting::create(['company_id' => $company->id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'auto', 'label' => 'Metode Insentif']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        return [$company, $pangkatA, $kelasA, $kelasB, $period];
    }

    public function test_auto_metode_pays_gap_between_legacy_and_new_allowance(): void
    {
        [, $pangkatA, $kelasA, , $period] = $this->makeSetup();

        LegacyPositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 50000]);
        PositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 40000]);

        $user = User::factory()->create(['company_id' => $period->company_id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id]);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(10000.0, $context->grossSalary);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'insentif', 'amount' => 10000]);
    }

    public function test_auto_metode_pays_nothing_after_moving_to_kelas_with_no_gap(): void
    {
        [, $pangkatA, $kelasA, $kelasB, $period] = $this->makeSetup();

        LegacyPositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 50000]);
        PositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 40000]);
        // Kelas B (naik kelas) tidak punya baris lama sama sekali -> tidak ada gap.
        PositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasB->id, 'amount' => 50000]);

        $user = User::factory()->create(['company_id' => $period->company_id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasB->id]);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'insentif']);
    }

    public function test_auto_metode_skips_when_profile_has_no_pangkat_or_kelas(): void
    {
        [, , , , $period] = $this->makeSetup();

        $user = User::factory()->create(['company_id' => $period->company_id]);
        UserProfiles::create(['user_id' => $user->id]);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new IncentiveCalculator())->calculate($payroll->user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }
}
