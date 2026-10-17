<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\LegacyPositionAllowance;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PositionAllowance;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end: PayrollService::generatePayroll() sungguhan (bukan panggil calculator langsung
 * seperti IncentiveCalculatorAutoMetodeTest) — dari isi matriks Tunjangan Jabatan lama/baru
 * sampai payroll_items & net_salary karyawan mencerminkan selisihnya.
 */
class InsentifAutoEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_payroll_pays_gap_as_insentif(): void
    {
        // Skenario persis dari contoh user: Pangkat A / Kelas A, lama 50.000, baru 40.000 -> selisih 10.000.
        $company = Companies::create(['code' => 'RS', 'name' => 'RS Test', 'location' => 'Test', 'is_active' => true]);

        $kelasA = Kelas::create(['name' => 'Kelas A', 'company_id' => $company->id, 'level' => 1]);
        $pangkatA = Pangkats::create(['name' => 'Pangkat A', 'company_id' => $company->id]);

        LegacyPositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 50000]);
        PositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 40000]);

        Setting::create(['company_id' => $company->id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'auto', 'label' => 'Metode Insentif']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::INSENTIF->value, 'is_active' => true]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::INSENTIF->value, 'employee_type_id' => $employeeType->id]);

        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create([
            'user_id' => $employee->id,
            'employee_type_id' => $employeeType->id,
            'pangkat_id' => $pangkatA->id,
            'kelas_id' => $kelasA->id,
        ]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);

        $payroll = Payroll::where('employee_id', $employee->employee->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => PayrollComponentEnum::INSENTIF->value,
            'amount' => 10000,
        ]);
        $this->assertSame(10000.0, (float) $payroll->gross_salary);
        $this->assertSame(10000.0, (float) $payroll->net_salary);

        fwrite(STDOUT, "\n[E2E] Tunjangan Jabatan lama=50000, baru=40000 -> Insentif (selisih) tergenerate = Rp" . number_format((float) $payroll->fresh()->items()->where('source_type', 'insentif')->value('amount'), 0, ',', '.') . "\n");
    }

    public function test_generate_payroll_pays_nothing_when_kelas_naik_ke_luar_rentang_lama(): void
    {
        // Skenario lanjutan dari diskusi: karyawan naik ke Pangkat A / Kelas B yang lebih tinggi,
        // tidak ada data lama untuk kombinasi itu -> insentif harus 0 (payroll_item TIDAK dibuat).
        $company = Companies::create(['code' => 'RS', 'name' => 'RS Test', 'location' => 'Test', 'is_active' => true]);

        $kelasA = Kelas::create(['name' => 'Kelas A', 'company_id' => $company->id, 'level' => 1]);
        $kelasB = Kelas::create(['name' => 'Kelas B', 'company_id' => $company->id, 'level' => 2]);
        $pangkatA = Pangkats::create(['name' => 'Pangkat A', 'company_id' => $company->id]);

        LegacyPositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 50000]);
        PositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasA->id, 'amount' => 40000]);
        PositionAllowance::create(['pangkat_id' => $pangkatA->id, 'kelas_id' => $kelasB->id, 'amount' => 50000]);
        // Sengaja TIDAK ada LegacyPositionAllowance untuk kelasB.

        Setting::create(['company_id' => $company->id, 'key' => 'insentif_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'auto', 'label' => 'Metode Insentif']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::INSENTIF->value, 'is_active' => true]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::INSENTIF->value, 'employee_type_id' => $employeeType->id]);

        $employee = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create([
            'user_id' => $employee->id,
            'employee_type_id' => $employeeType->id,
            'pangkat_id' => $pangkatA->id,
            'kelas_id' => $kelasB->id,
        ]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);

        $payroll = Payroll::where('employee_id', $employee->employee->id)->where('payroll_period_id', $period->id)->firstOrFail();

        // Insentif tetap dapat baris Rp0 (bukan hilang total) - komponen ini aktif+eligible,
        // cuma hasil hitungnya nol (2026-08-02, "semua komponen mesti digenerate meskipun kosong").
        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => PayrollComponentEnum::INSENTIF->value,
            'amount' => 0,
        ]);
        $this->assertSame(0.0, (float) $payroll->gross_salary);

        fwrite(STDOUT, "[E2E] Naik ke Pangkat A / Kelas B (tidak ada data lama) -> Insentif (selisih) = Rp0, sesuai keputusan user\n");
    }
}
