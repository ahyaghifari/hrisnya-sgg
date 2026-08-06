<?php

namespace Tests\Feature\Payroll;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\Calculators\IzinSeharianCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rumus dikonfirmasi user dari Excel (analisis-payroll-4 §7B.3): rate_harian = T8 / hari dalam
 * bulan periode, potongan = rate_harian * jumlah kejadian. Belum pernah punya test unit sejak
 * dibangun di Fase 2 (lihat planning/2026-07-17-payroll-fase-2-...md §7.2) — mengisi celah itu,
 * bukan menggantikan test lama (SalaryComponentAssignmentTest, yang menguji CRUD assignment
 * pada arsitektur salary_components yang sudah dihapus total).
 */
class IzinSeharianCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makePeriod(string $companyId, string $startDate, string $endDate): PayrollPeriod
    {
        return PayrollPeriod::create([
            'company_id' => $companyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    private function makeApprovedIzinSeharian(User $user, string $companyId, string $date): void
    {
        $permissionType = PermissionTypes::create([
            'company_id' => $companyId,
            'name' => 'Izin Seharian',
            'category' => PermissionTypeCategoryEnum::IZIN_SEHARIAN->value,
        ]);

        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'permission',
            'status' => AttendanceReqStatus::APPROVED->value,
        ]);

        AttendanceRequestPermissionDetails::create([
            'request_id' => $request->id,
            'permission_type_id' => $permissionType->id,
            'category' => $permissionType->category,
            'date' => $date,
        ]);
    }

    public function test_calculates_deduction_from_gross_salary_and_days_in_month(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-07-01', '2026-07-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-07-10');

        $context = new PayrollContext();
        $context->addEarning(3_100_000); // T8, dibulatkan biar 31 hari (Juli) pas dibagi

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        // rate_harian = 3.100.000 / 31 hari = 100.000, 1 kejadian => potongan 100.000
        $this->assertSame(100_000.0, $context->totalDeductions);
        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'izin_seharian',
            'quantity' => 1,
            'amount' => 100_000,
        ]);
    }

    public function test_multiple_occurrences_are_summed(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-07-01', '2026-07-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-07-10');
        $this->makeApprovedIzinSeharian($user, $company->id, '2026-07-15');

        $context = new PayrollContext();
        $context->addEarning(3_100_000);

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        // rate_harian 100.000 * 2 kejadian = 200.000
        $this->assertSame(200_000.0, $context->totalDeductions);
    }

    public function test_occurrence_outside_period_is_ignored(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-07-01', '2026-07-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-08-01');

        $context = new PayrollContext();
        $context->addEarning(3_100_000);

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }

    public function test_uses_attendance_period_window_not_literal_payroll_period_dates(): void
    {
        // Sama seperti kasus Izin Meninggalkan Jam Kerja: window pengecekan harus ikut
        // Pengaturan Absensi (21-20), bukan literal start_date/end_date PayrollPeriod (1-31
        // kalender yang diketik admin).
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_start_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '21', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_end_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '20', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-05-01', '2026-05-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-04-15'); // sebelum window - JANGAN kehitung
        $this->makeApprovedIzinSeharian($user, $company->id, '2026-05-14'); // di dalam window 21 Apr - 20 Mei - HARUS kehitung
        $this->makeApprovedIzinSeharian($user, $company->id, '2026-05-25'); // setelah window (window salah lama: Mei21-Jun20) - JANGAN kehitung

        $context = new PayrollContext();
        $context->addEarning(3_100_000);

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        // Cuma 1 kejadian (14 Mei) yang kehitung.
        $this->assertSame(100_000.0, $context->totalDeductions);
    }

    public function test_no_deduction_when_gross_salary_is_zero(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-07-01', '2026-07-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-07-10');

        $context = new PayrollContext(); // grossSalary tetap 0 — belum ada earning terkumpul

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }

    public function test_gaji_pokok_metode_uses_gaji_pokok_payroll_item_not_gross(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_seharian_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'gaji_pokok', 'label' => 'Metode Izin Seharian']);
        // Pembagi sekarang ikut prorate_metode (ResolvesProrateDivisor, bukan selalu hari kerja
        // efektif lagi) - set eksplisit ke 'hari_kerja_efektif' biar test ini tetap menguji jalur itu.
        Setting::create(['company_id' => $company->id, 'key' => 'prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'hari_kerja_efektif', 'label' => 'Metode Pembagi Prorate Karyawan Baru']);
        Setting::create(['company_id' => $company->id, 'key' => 'kepegawaian_hari_kerja_efektif_per_bulan', 'module' => 'kepegawaian', 'type' => 'integer', 'value' => '26', 'label' => 'Hari Kerja Efektif per Bulan']);
        // persen dibiarkan default (70) - tidak di-seed, harus fallback ke default enum.

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-07-01', '2026-07-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        // Baris Gaji Pokok yang SEHARUSNYA sudah dibuat GajiPokokCalculator sebelum Izin Seharian
        // jalan (urutan orchestrator PayrollService) - disimulasikan langsung di sini.
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'quantity' => 1,
            'amount' => 3_000_000,
            'source_type' => 'gaji_pokok',
        ]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-07-10');

        // Gross salary tinggi sengaja diisi buat pastikan metode 'gaji_pokok' TIDAK terpengaruh
        // gross - kalau salah pakai formula 'keseluruhan', hasilnya akan beda jauh.
        $context = new PayrollContext();
        $context->addEarning(50_000_000);

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        // (3.000.000 / 26) * 70% = 80.769,23..., dibulatkan 2 desimal
        $expected = round((3_000_000 / 26) * 0.7, 2);
        $this->assertSame($expected, $context->totalDeductions);
    }

    public function test_gaji_pokok_metode_defaults_to_calendar_days_when_prorate_metode_not_set(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'izin_seharian_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'gaji_pokok', 'label' => 'Metode Izin Seharian']);
        // prorate_metode SENGAJA tidak di-set - default 'kalender', BUKAN lagi selalu hari kerja efektif.

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-07-01', '2026-07-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        PayrollItem::create([
            'payroll_id' => $payroll->id, 'name' => 'Gaji Pokok', 'type' => 'earning',
            'quantity' => 1, 'amount' => 3_100_000, 'source_type' => 'gaji_pokok',
        ]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-07-10');

        $context = new PayrollContext();
        $context->addEarning(50_000_000); // dibuktikan tidak dipakai, sama seperti test di atas

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        // (3.100.000 / 31 hari kalender Juli) * 70% = 70.000
        $this->assertSame(70_000.0, $context->totalDeductions);
    }

    public function test_keseluruhan_metode_uses_hari_kerja_efektif_when_configured(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'hari_kerja_efektif', 'label' => 'Metode Pembagi Prorate Karyawan Baru']);
        Setting::create(['company_id' => $company->id, 'key' => 'kepegawaian_hari_kerja_efektif_per_bulan', 'module' => 'kepegawaian', 'type' => 'integer', 'value' => '25', 'label' => 'Hari Kerja Efektif per Bulan']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = $this->makePeriod($company->id, '2026-07-01', '2026-07-31');
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $this->makeApprovedIzinSeharian($user, $company->id, '2026-07-10');

        $context = new PayrollContext();
        $context->addEarning(2_500_000);

        (new IzinSeharianCalculator())->calculate($user, $payroll, $period, $context);

        // rate_harian = 2.500.000 / 25 (hari kerja efektif, BUKAN 31 hari kalender Juli) = 100.000
        $this->assertSame(100_000.0, $context->totalDeductions);
    }
}
