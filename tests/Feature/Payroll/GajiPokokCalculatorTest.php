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

    public function test_calculates_flat_increase_from_company_setting(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '8', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $kelas3 = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        $kelas5 = Kelas::create(['name' => 'Kelas V', 'company_id' => $company->id, 'level' => 5]);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas5->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);
        $tetap = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id, 'category' => 'tetap']);

        $user = User::factory()->create(['company_id' => $company->id]);
        // Kelas III (level 3, di tengah rentang Kelas I-V Pangkat ini) - kenaikan berdasarkan
        // langkah kelas di-terminate 2026-08-22, jadi Kelas TIDAK lagi mempengaruhi nominal,
        // cuma rate company (flat 8%) yang berlaku, siapapun kelasnya.
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas3->id, 'employee_type_id' => $tetap->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        // Flat 8%, lepas dari kelas: 3.000.000 * 1.08
        $this->assertSame(3_240_000.0, $context->grossSalary);
    }

    /** Rate CUMA berlaku untuk Tetap & Kontrak (instruksi user 2026-08-22) - Orientasi tidak dapat
     * kenaikan sama sekali walau company sudah setting 8%. */
    public function test_orientasi_gets_no_increase_regardless_of_company_setting(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '8', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

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

    public function test_override_enum_takes_precedence_over_company_setting(): void
    {
        $company = Companies::create(['code' => '0000', 'name' => 'Holding Test', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '8', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);
        $tetap = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id, 'category' => 'tetap']);

        $overrideId = \App\Enum\Payroll\GajiPokokKenaikanOverrideEnum::ABDURRAHMAN_HAKIM->value;
        $user = new User();
        $user->forceFill(['id' => $overrideId, 'name' => 'Abdurrahman Hakim Uji', 'email' => 'hakim-override@test.local', 'password' => bcrypt('secret'), 'nip' => '00098', 'company_id' => $company->id, 'is_active' => true]);
        $user->save();
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $tetap->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        // Override 16% (bukan 8% dari setting company): 3.000.000 * 1.16
        $this->assertSame(3_480_000.0, $context->grossSalary);
    }

    /** start_kelas_id/max_kelas_id kosong tetap kehitung normal - keduanya sudah tidak dipakai
     * di rumus sejak kenaikan berdasarkan langkah kelas di-terminate (2026-08-22). */
    public function test_calculates_normally_when_pangkat_has_no_start_kelas_mapped(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '8', 'label' => 'Kenaikan Gaji Pokok per Kelas']);
        $kelas = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]); // start_kelas_id/max_kelas_id kosong
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);
        $tetap = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id, 'category' => 'tetap']);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'employee_type_id' => $tetap->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(3_240_000.0, $context->grossSalary);
    }

    /** Fail-safe (2026-08-25): employee_type_id belum di-assign (null, data belum lengkap) HARUS
     * dapat rate 0%, bukan diam-diam dapat rate penuh cuma karena bukan Orientasi secara
     * eksplisit - sebelumnya null lolos ke cabang "dapat kenaikan" yang sama dengan Tetap/Kontrak. */
    public function test_no_increase_when_employee_type_not_assigned(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '8', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        // employee_type_id sengaja kosong.
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(3_000_000.0, $context->grossSalary);
    }

    public function test_skips_when_kelas_not_assigned(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id]); // kelas_id kosong

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }

    public function test_prorates_when_joining_date_falls_mid_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        // Rate 0% biar hitungan prorate murni, tidak ikut kena default 8%.
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

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

        // 5 hari kerja / 31 hari (Juli) * 3.000.000 - pembagi jumlah hari kalender bulan bergabung.
        $this->assertSame(round(3_000_000 * 5 / 31, 2), $context->grossSalary);
    }

    public function test_no_prorate_when_joining_date_before_period_start(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

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

    public function test_prorates_using_hari_kerja_efektif_divisor_when_configured(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'hari_kerja_efektif', 'label' => 'Metode Pembagi Prorate Karyawan Baru']);
        Setting::create(['company_id' => $company->id, 'key' => 'kepegawaian_hari_kerja_efektif_per_bulan', 'module' => 'kepegawaian', 'type' => 'integer', 'value' => '25', 'label' => 'Hari Kerja Efektif per Bulan']);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'joining_date' => '2026-07-27']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new GajiPokokCalculator())->calculate($user, $payroll, $period, $context);

        // 5 hari kerja / 25 (setting Hari Kerja Efektif per Bulan) * 3.000.000 - BUKAN dibagi 31
        // (hari kalender Juli) karena company ini pakai metode 'hari_kerja_efektif'.
        $this->assertSame(round(3_000_000 * 5 / 25, 2), $context->grossSalary);
    }

    public function test_prorates_regardless_of_employee_category(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

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

        // 5 hari kerja / 31 hari (Juli) * 3.000.000
        $this->assertSame(round(3_000_000 * 5 / 31, 2), $context->grossSalary);
    }
}
