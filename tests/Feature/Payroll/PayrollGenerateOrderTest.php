<?php

namespace Tests\Feature\Payroll;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PayrollService::generateForUser() WAJIB jalankan semua komponen EARNING (termasuk Gaji Pokok)
 * SEBELUM Izin Seharian - metode 'keseluruhan' butuh $context->grossSalary yang sudah final
 * (lihat komentar di generateForUser()). Test ini mengunci OUTCOME dari urutan itu: kalau ada
 * yang menyederhanakan orchestrator jadi 1 loop biasa (Izin Seharian ikut urutan alami komponen,
 * bisa jalan sebelum Gaji Pokok), potongan Izin Seharian di sini akan salah (lebih kecil/nol),
 * bukan cuma teori.
 */
class PayrollGenerateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_izin_seharian_keseluruhan_deducts_from_final_gross_including_gaji_pokok(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_100_000]); // biar pas dibagi 31 hari Juli

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::IZIN_SEHARIAN->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::IZIN_SEHARIAN->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $employeeType->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $permissionType = PermissionTypes::create(['company_id' => $company->id, 'name' => 'Izin Seharian', 'category' => PermissionTypeCategoryEnum::IZIN_SEHARIAN->value]);
        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestPermissionDetails::create(['request_id' => $request->id, 'permission_type_id' => $permissionType->id, 'category' => PermissionTypeCategoryEnum::IZIN_SEHARIAN->value, 'date' => '2026-07-10']);

        app(PayrollService::class)->generatePayroll($period);

        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        // Gaji Pokok penuh (kelas1 = startKelas pangkat, 0 langkah kenaikan) = 3.100.000.
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'gaji_pokok', 'amount' => 3_100_000]);

        // rate_harian = 3.100.000 / 31 hari (Juli) = 100.000, 1 kejadian izin -> potongan 100.000.
        // Kalau Izin Seharian jalan SEBELUM Gaji Pokok (order broken), gross saat itu masih 0,
        // potongannya akan 0 - assert ini gagal duluan sebagai sinyal jelas.
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'izin_seharian', 'amount' => 100_000]);

        $izinItem = PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'izin_seharian')->first();
        $this->assertNotNull($izinItem, 'Izin Seharian tidak boleh Rp0/tidak ada - berarti urutan Gaji Pokok sebelum Izin Seharian rusak.');

        $this->assertSame(3_100_000.0, (float) $payroll->gross_salary);
        $this->assertSame(100_000.0, (float) $payroll->total_deductions);
        $this->assertSame(3_000_000.0, (float) $payroll->net_salary);
    }
}
