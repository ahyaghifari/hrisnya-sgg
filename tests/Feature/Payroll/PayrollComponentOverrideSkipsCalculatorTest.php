<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\PayrollComponentOverride;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman "Override Komponen Gaji" (payroll_component_overrides) - kalau ada override AKTIF
 * untuk (user, component), Calculator komponen itu SAMA SEKALI TIDAK dijalankan, nilai override
 * dipakai apa adanya - lihat PayrollService::runComponent(). Beda dari
 * PayrollManualAdjustmentSurvivesRegenerateTest (itu proteksi baris yang SUDAH digenerate lalu
 * diedit manual; ini standing policy yang dicek SEBELUM baris pertama kali dibuat).
 */
class PayrollComponentOverrideSkipsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_override_replaces_calculator_result(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        // Kalkulator otomatis akan hasilkan 3.100.000 kalau override TIDAK mencegahnya jalan.
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_100_000]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $employeeType->id]);

        PayrollComponentOverride::create([
            'user_id' => $user->id,
            'component' => PayrollComponentEnum::GAJI_POKOK->value,
            'amount' => 9_999_000,
            'note' => 'kesepakatan khusus',
            'is_active' => true,
        ]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $payroll = app(PayrollService::class)->generateSingleForUser($user, $period);

        $item = $payroll->items()->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)->first();

        // Bukan 3.100.000 (hasil Calculator) - override yang harus dipakai apa adanya.
        $this->assertSame(9_999_000.0, (float) $item->amount);
        $this->assertSame('kesepakatan khusus', $item->note);
        $this->assertSame(1, $payroll->items()->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)->count());
        $this->assertSame(9_999_000.0, (float) $payroll->fresh()->gross_salary);
    }

    public function test_inactive_override_is_ignored(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_100_000]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $employeeType->id]);

        PayrollComponentOverride::create([
            'user_id' => $user->id,
            'component' => PayrollComponentEnum::GAJI_POKOK->value,
            'amount' => 9_999_000,
            'is_active' => false,
        ]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $payroll = app(PayrollService::class)->generateSingleForUser($user, $period);

        $item = $payroll->items()->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)->first();

        // Override nonaktif diabaikan - Calculator jalan normal.
        $this->assertSame(3_100_000.0, (float) $item->amount);
    }
}
