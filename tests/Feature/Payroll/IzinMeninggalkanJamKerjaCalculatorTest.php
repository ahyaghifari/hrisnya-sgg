<?php

namespace Tests\Feature\Payroll;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\Calculators\IzinMeninggalkanJamKerjaCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gabungan 3 kategori (Izin Terlambat Masuk, Izin Pulang Cepat, Izin Meninggalkan Jam Kerja
 * tengah hari) jadi 1 komponen - potongan flat per kejadian, TANPA kompensasi/grace.
 */
class IzinMeninggalkanJamKerjaCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedIzin(User $user, string $companyId, string $category, string $date): void
    {
        $permissionType = PermissionTypes::create(['company_id' => $companyId, 'name' => $category, 'category' => $category]);
        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestPermissionDetails::create(['request_id' => $request->id, 'permission_type_id' => $permissionType->id, 'category' => $category, 'date' => $date]);
    }

    public function test_charges_flat_amount_per_occurrence(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value, '2026-07-05');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(10_000.0, $context->totalDeductions);
    }

    public function test_three_categories_are_combined_into_one_component(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value, '2026-07-05');
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value, '2026-07-10');
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA->value, '2026-07-15');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

        // No kompensasi/grace - 3 kejadian x 10.000 = 30.000, tanpa ada yang digratiskan.
        $this->assertSame(30_000.0, $context->totalDeductions);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'quantity' => 3, 'amount' => 30000]);
    }

    public function test_izin_sakit_category_not_counted(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        // Kategori sakit BUKAN salah satu dari 3 kategori komponen ini.
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_SAKIT->value, '2026-07-05');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
    }

    public function test_uses_attendance_period_window_not_literal_payroll_period_dates(): void
    {
        // Reproduksi laporan bug: PayrollPeriod diketik admin sebagai kalender penuh (1-31 Mei),
        // tapi company ini pakai window Periode Absensi 21-20 (21 April s.d. 20 Mei). Izin
        // tanggal 14 Mei harus tetap kehitung (ada di kedua window), dan izin di luar window
        // 21-20 (mis. tanggal 25 Mei, di luar rentang Apr21-Mei20 tapi masih "di dalam" kalender
        // Mei) harus TIDAK kehitung di periode Mei ini - baru masuk periode Juni.
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_start_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '21', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_end_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '20', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-05-01', 'end_date' => '2026-05-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value, '2026-05-14');
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value, '2026-05-25');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

        // Cuma 1 kejadian (14 Mei) yang kehitung, 25 Mei di luar window 21 Apr - 20 Mei.
        $this->assertSame(10_000.0, $context->totalDeductions);
    }

    public function test_no_flat_setting_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value, '2026-07-05');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
    }
}
