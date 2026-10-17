<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug yang dilaporkan user: tombol "Generate" di halaman Periode Gaji skip total karyawan yang
 * sudah punya baris payroll di periode itu (payrollAlreadyExists() lama) - jadi fix Calculator
 * (mis. prorate Gaji Pokok, window Izin) tidak pernah kepakai buat payroll yang kebetulan sudah
 * ada baris lamanya, cuma "Regenerate" (yang eksplisit hapus dulu) yang kena. Sekarang
 * generatePayroll() harus REFRESH baris yang belum dibayar (bukan skip), dan tetap skip yang
 * sudah dibayar (2026-08-02).
 */
class GeneratePayrollRefreshesUnpaidTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $employeeType->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        return compact('company', 'pangkat', 'kelas1', 'user', 'period');
    }

    public function test_generate_again_refreshes_unpaid_payroll_instead_of_skipping(): void
    {
        $s = $this->makeSetup();
        $service = app(PayrollService::class);

        $service->generatePayroll($s['period']);
        $firstPayroll = Payroll::where('employee_id', $s['user']->employee->id)->where('payroll_period_id', $s['period']->id)->firstOrFail();
        $firstPayrollId = $firstPayroll->id;

        // Data karyawan berubah SETELAH generate pertama (mis. kelas naik) - kalau "Generate"
        // lagi cuma skip karena baris sudah ada, gaji_pokok akan tetap 3.000.000 (nilai lama).
        $kelas2 = Kelas::create(['name' => 'Kelas II', 'company_id' => $s['company']->id, 'level' => 2]);
        $s['pangkat']->update(['max_kelas_id' => $kelas2->id]);
        \App\Models\Setting::create(['company_id' => $s['company']->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10', 'label' => 'x']);
        $s['user']->profile->update(['kelas_id' => $kelas2->id]);

        $service->generatePayroll($s['period']);

        $refreshedPayroll = Payroll::where('employee_id', $s['user']->employee->id)->where('payroll_period_id', $s['period']->id)->firstOrFail();

        // Baris payroll baru (ID beda - dihapus & dibikin ulang), bukan baris lama yang di-skip.
        $this->assertNotSame($firstPayrollId, $refreshedPayroll->id);
        // Gaji Pokok ikut nilai TERBARU (3.000.000 * 1.1 = 3.300.000), bukan nilai lama.
        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $refreshedPayroll->id,
            'source_type' => 'gaji_pokok',
            'amount' => 3_300_000,
        ]);
    }

    public function test_generate_again_does_not_touch_paid_payroll(): void
    {
        $s = $this->makeSetup();
        $service = app(PayrollService::class);

        $service->generatePayroll($s['period']);
        $paidPayroll = Payroll::where('employee_id', $s['user']->employee->id)->where('payroll_period_id', $s['period']->id)->firstOrFail();
        $paidPayroll->update(['is_paid' => true]);

        $service->generatePayroll($s['period']);

        // Payroll yang sudah dibayar tetap sama persis (ID & data), tidak dihapus/diregenerate.
        $this->assertDatabaseHas('payrolls', ['id' => $paidPayroll->id, 'is_paid' => true]);
        $this->assertSame(1, Payroll::where('payroll_period_id', $s['period']->id)->count());
    }
}
