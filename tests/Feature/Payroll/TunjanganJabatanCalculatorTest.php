<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PositionAllowance;
use App\Models\Setting;
use App\Models\User;
use App\Models\Employee\EmployeeContract;
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
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

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
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }

    public function test_prorates_when_joining_date_falls_mid_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        PositionAllowance::create(['pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'amount' => 750000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'joining_date' => '2026-07-27']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        // 5 hari kerja (27-31 Juli) / 31 hari kalender Juli * 750.000 - metode default 'kalender'.
        $this->assertSame(round(750_000 * 5 / 31, 2), $context->grossSalary);
    }

    public function test_prorates_using_hari_kerja_efektif_when_configured(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'hari_kerja_efektif', 'label' => 'Metode Pembagi Prorate Karyawan Baru']);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        PositionAllowance::create(['pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'amount' => 750000]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'joining_date' => '2026-07-27']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        // Default Hari Kerja Efektif per Bulan = 25 (Pengaturan Kepegawaian, tidak di-override).
        $this->assertSame(round(750_000 * 5 / 25, 2), $context->grossSalary);
    }

    public function test_missing_pangkat_or_kelas_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id]); // tanpa pangkat/kelas

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
    }

    /** Transisi Orientasi->Kontrak (instruksi user 2026-09-15, mode 'tiered') - beda dari Gaji
     * Pokok: Orientasi tidak dapat Tunjangan Jabatan sama sekali (Rp0), bukan base_salary polos. */
    public function test_tiered_transition_pays_nothing_before_ttd_full_after_when_signed_day_6_to_20(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'kontrak_prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'tiered', 'label' => 'Metode Gaji Bulan TTD Kontrak']);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        PositionAllowance::create(['pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'amount' => 750000]);

        $kontrak = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id, 'category' => 'kontrak']);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'employee_type_id' => $kontrak->id, 'joining_date' => '2025-01-01']);
        EmployeeContract::create(['employee_id' => $user->employee->id, 'type' => 'kontrak', 'company_id' => $company->id, 'employee_type_id' => $kontrak->id, 'start_date' => '2026-07-10', 'end_date' => '2027-07-09']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        // 10-31 Juli = 19 hari kerja (Minggu 12/19/26 di-exclude) / 31 hari kalender * 750.000 -
        // porsi sebelum TTD (1-9 Juli) Rp0, tidak ikut dihitung sama sekali.
        $this->assertSame(round(750_000 * 19 / 31, 2), $context->grossSalary);
    }

    public function test_tiered_transition_pays_full_when_signed_day_1_to_5(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'kontrak_prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'tiered', 'label' => 'Metode Gaji Bulan TTD Kontrak']);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        PositionAllowance::create(['pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'amount' => 750000]);

        $kontrak = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id, 'category' => 'kontrak']);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'employee_type_id' => $kontrak->id, 'joining_date' => '2025-01-01']);
        EmployeeContract::create(['employee_id' => $user->employee->id, 'type' => 'kontrak', 'company_id' => $company->id, 'employee_type_id' => $kontrak->id, 'start_date' => '2026-07-03', 'end_date' => '2027-07-02']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(750_000.0, $context->grossSalary);
    }

    public function test_tiered_transition_pays_nothing_when_signed_after_day_20(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'kontrak_prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'tiered', 'label' => 'Metode Gaji Bulan TTD Kontrak']);

        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        $kelas = Kelas::create(['name' => 'Kelas III', 'company_id' => $company->id, 'level' => 3]);
        PositionAllowance::create(['pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'amount' => 750000]);

        $kontrak = EmployeeTypes::create(['name' => 'Kontrak', 'company_id' => $company->id, 'category' => 'kontrak']);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'employee_type_id' => $kontrak->id, 'joining_date' => '2025-01-01']);
        EmployeeContract::create(['employee_id' => $user->employee->id, 'type' => 'kontrak', 'company_id' => $company->id, 'employee_type_id' => $kontrak->id, 'start_date' => '2026-07-25', 'end_date' => '2027-07-24']);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        (new TunjanganJabatanCalculator())->calculate($payroll->employee, $payroll, $period, $context);

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }
}
