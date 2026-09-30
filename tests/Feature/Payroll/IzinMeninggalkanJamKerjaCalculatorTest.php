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

    private function makeApprovedIzin(User $user, string $companyId, string $category, string $date, ?string $startTime = null, ?string $endTime = null): void
    {
        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestPermissionDetails::create([
            'request_id' => $request->id,
            'category' => $category,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
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

    /** izin_potongan_per_jam=true - rate dikali jumlah jam izin (dari start_time/end_time),
     * bukan flat per kejadian lagi. Unit bisnis yang hitungnya kelipatan jam (instruksi user
     * 2026-09-29/30). */
    public function test_multiplies_by_hours_when_per_jam_setting_enabled(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_per_jam', 'module' => 'payroll', 'type' => 'boolean', 'value' => '1', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        // Izin 2 jam (10:00-12:00) - 10.000 x 2 jam = 20.000, bukan flat 10.000.
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA->value, '2026-07-05', '10:00:00', '12:00:00');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(20_000.0, $context->totalDeductions);
    }

    /** 2 kejadian beda durasi digabung jamnya, bukan cuma dihitung kejadian - 2 jam + 1 jam = 3 jam. */
    public function test_sums_hours_across_multiple_occurrences_when_per_jam_enabled(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_per_jam', 'module' => 'payroll', 'type' => 'boolean', 'value' => '1', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value, '2026-07-05', '07:00:00', '09:00:00');
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value, '2026-07-10', '15:00:00', '16:00:00');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

        // (2 jam + 1 jam) x 10.000 = 30.000.
        $this->assertSame(30_000.0, $context->totalDeductions);
    }

    /** izin_potongan_per_jam default/false - berapa jam pun izinnya, tetap flat (perilaku lama
     * tidak berubah kalau setting ini tidak diaktifkan). */
    public function test_stays_flat_regardless_of_hours_when_per_jam_setting_disabled(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_potongan_flat', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10000', 'label' => 'x']);
        // izin_potongan_per_jam TIDAK dibuat sama sekali - default Setting::resolve() = false.

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        // Izin 4 jam - tetap 10.000 flat, bukan 40.000.
        $this->makeApprovedIzin($user, $company->id, PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA->value, '2026-07-05', '09:00:00', '13:00:00');

        $context = new PayrollContext();
        (new IzinMeninggalkanJamKerjaCalculator())->calculate($user, $payroll, $period, $context);

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
