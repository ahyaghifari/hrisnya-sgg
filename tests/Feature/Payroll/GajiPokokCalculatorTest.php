<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
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

    public function test_prorates_when_joining_date_falls_mid_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'kepegawaian_hari_kerja_efektif_per_bulan', 'module' => 'kepegawaian', 'type' => 'integer', 'value' => '26', 'label' => 'Hari Kerja Efektif per Bulan']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);
        $orientasi = EmployeeTypes::create(['name' => 'Orientasi', 'company_id' => $company->id, 'category' => 'orientasi']);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $orientasi->id, 'joining_date' => '2026-07-27']);

        // Periode 1-31 Juli 2026. Join 27 Juli s.d. 31 Juli = 5 hari kalender, Minggu jatuh di
        // 26 & 2 Agustus (di luar rentang) - tidak ada yang di-exclude, worked days = 5.
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        // 5 hari kerja / 26 hari kerja efektif per bulan * 3.000.000
        $this->assertSame(round(3_000_000 * 5 / 26, 2), $context->grossSalary);
    }

    public function test_no_prorate_when_joining_date_before_period_start(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);
        $orientasi = EmployeeTypes::create(['name' => 'Orientasi', 'company_id' => $company->id, 'category' => 'orientasi']);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $orientasi->id, 'joining_date' => '2026-01-01']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(3_000_000.0, $context->grossSalary);
    }

    public function test_prorates_regardless_of_employee_category(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'kepegawaian_hari_kerja_efektif_per_bulan', 'module' => 'kepegawaian', 'type' => 'integer', 'value' => '26', 'label' => 'Hari Kerja Efektif per Bulan']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);
        $tetap = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id, 'category' => 'tetap']);

        $user = User::factory()->create(['company_id' => $company->id]);
        // Joining date jatuh di tengah periode, kategori Tetap - tetap harus di-prorate karena
        // prorate sekarang murni berdasar jarak tanggal bergabung ke akhir periode, lepas dari kategori.
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $tetap->id, 'joining_date' => '2026-07-27']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        // 5 hari kerja / 26 hari kerja efektif per bulan * 3.000.000
        $this->assertSame(round(3_000_000 * 5 / 26, 2), $context->grossSalary);
    }
}
