<?php

namespace Tests\Feature\Payroll;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
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
        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestPermissionDetails::create(['request_id' => $request->id, 'category' => $category, 'date' => $date]);
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
        // tapi company ini pakai window Periode Absensi 21-20, jadi periode Izin buat "Mei"
        // seharusnya 21 April s.d. 20 Mei. 3 tanggal dipasang sekaligus biar assert-nya ngga
        // ambigu (versi awal fix ini pernah salah anchor - geser sebulan ke depan jadi
        // 21 Mei - 20 Juni - tapi test lama kebetulan tetap lolos karena masing-masing skenario
        // sama-sama cuma nangkep 1 dari 2 tanggal; sekarang dites eksplisit ada di window benar
        // DAN tidak ada di window yang salah).
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_start_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '21', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_end_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '20', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-05-01', 'end_date' => '2026-05-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value, '2026-04-15'); // sebelum window (Apr21-Mei20) - JANGAN kehitung
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value, '2026-05-14'); // di dalam window - HARUS kehitung
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value, '2026-05-25'); // setelah window (window salah lama: Mei21-Jun20) - JANGAN kehitung

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
