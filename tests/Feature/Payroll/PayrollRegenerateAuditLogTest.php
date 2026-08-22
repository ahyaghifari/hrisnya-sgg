<?php

namespace Tests\Feature\Payroll;

use App\Enum\EmployeeTypeCategory;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * regenerateSinglePayroll()/regeneratePayroll() menghapus baris Payroll lama & bikin baru dari
 * nol lewat Calculator (cascadeOnDelete payroll_items) - TIDAK ADA jejak apa pun sebelum
 * perubahan ini. Test ini pakai fixture lengkap (Pangkat/Kelas/PangkatSalary/setting kenaikan/
 * PayrollComponentRecipient) supaya GajiPokokCalculator BENERAN jalan, bukan cuma cek angka di
 * memori - biar yakin logRegenerateChangeIfAny() kepanggil di jalur regenerate sungguhan.
 */
class PayrollRegenerateAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixture(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        Setting::create(['key' => 'gaji_pokok_kenaikan_per_kelas', 'company_id' => $company->id, 'value' => '8', 'type' => 'integer', 'module' => 'payroll', 'label' => 'Kenaikan per Kelas']);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'category' => EmployeeTypeCategory::TETAP, 'company_id' => $company->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $startKelas = Kelas::create(['company_id' => $company->id, 'name' => 'Kelas 1', 'level' => 1]);
        $currentKelas = Kelas::create(['company_id' => $company->id, 'name' => 'Kelas 3', 'level' => 3]);
        $pangkat = Pangkats::create(['company_id' => $company->id, 'name' => 'Staff', 'start_kelas_id' => $startKelas->id, 'max_kelas_id' => $currentKelas->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 1_000_000]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'payment_date' => now()]);

        $user = User::create(['name' => 'Karyawan Uji', 'email' => 'uji@test.local', 'password' => bcrypt('secret'), 'nip' => '00123', 'company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $currentKelas->id, 'employee_type_id' => $employeeType->id, 'joining_date' => now()->subYear()]);

        return compact('company', 'period', 'user', 'pangkat', 'currentKelas');
    }

    public function test_regenerate_single_payroll_logs_when_totals_change(): void
    {
        $this->actingAsAuditableUser();
        $fixture = $this->makeFixture();

        $service = app(PayrollService::class);
        $payroll = $service->generateSingleForUser($fixture['user'], $fixture['period']);
        $this->assertSame(1_080_000.0, (float) $payroll->fresh()->gross_salary); // base 1jt flat x8% = 1,08jt (kelas tidak lagi berpengaruh)

        // Ubah basis gaji pokok company ini - regenerate seharusnya menghasilkan angka BEDA,
        // dan itu HARUS kecatat ke audit_logs.
        PangkatSalary::where('pangkat_id', $fixture['pangkat']->id)->update(['base_salary' => 2_000_000]);

        $newPayroll = $service->regenerateSinglePayroll($payroll->fresh(['payrollPeriod', 'user']));

        $this->assertSame(2_160_000.0, (float) $newPayroll->fresh()->gross_salary);

        $log = AuditLog::where('module', 'payroll_regenerate')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('1.080.000', $log->old_value);
        $this->assertStringContainsString('2.160.000', $log->new_value);
    }

    public function test_regenerate_single_payroll_does_not_log_when_totals_unchanged(): void
    {
        $this->actingAsAuditableUser();
        $fixture = $this->makeFixture();

        $service = app(PayrollService::class);
        $payroll = $service->generateSingleForUser($fixture['user'], $fixture['period']);

        // Regenerate TANPA mengubah data master apa pun - angkanya harus tetap sama, tidak
        // perlu dicatat sebagai "perubahan".
        $service->regenerateSinglePayroll($payroll->fresh(['payrollPeriod', 'user']));

        $this->assertSame(0, AuditLog::where('module', 'payroll_regenerate')->count());
    }

    public function test_bulk_regenerate_period_logs_per_changed_employee(): void
    {
        $this->actingAsAuditableUser();
        $fixture = $this->makeFixture();

        $service = app(PayrollService::class);
        $service->generatePayroll($fixture['period']);

        $before = Payroll::where('payroll_period_id', $fixture['period']->id)->first();
        $this->assertNotNull($before);
        $this->assertSame(1_080_000.0, (float) $before->gross_salary);

        PangkatSalary::where('pangkat_id', $fixture['pangkat']->id)->update(['base_salary' => 3_000_000]);

        $service->regeneratePayroll($fixture['period']->fresh());

        $after = Payroll::where('payroll_period_id', $fixture['period']->id)->first();
        $this->assertSame(3_240_000.0, (float) $after->gross_salary);

        $this->assertSame(1, AuditLog::where('module', 'payroll_regenerate')->count());
    }

    private function actingAsAuditableUser(): void
    {
        $actor = User::create(['name' => 'Actor', 'email' => 'actor@test.local', 'password' => bcrypt('secret'), 'is_active' => true]);
        $this->actingAs($actor);
    }
}
