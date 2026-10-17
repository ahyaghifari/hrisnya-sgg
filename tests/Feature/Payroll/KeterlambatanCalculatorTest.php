<?php

namespace Tests\Feature\Payroll;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\Calculators\KeterlambatanCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Denda flat per kejadian telat (toleransi sudah diterapkan sekali di level jadwal -
 * FixedScheduleCalculator, status cuma 'late' kalau sudah lewat toleransi jadwal - di sini
 * tidak ada threshold menit kedua, late_minutes manapun yang > 0 dianggap telat), dengan
 * kompensasi (grace): selama masih <= grace count TIDAK ada potongan sama sekali; begitu lolos
 * grace, SEMUA kejadian telat bulan itu kena denda penuh (bukan cuma kelebihan di atas grace).
 */
class KeterlambatanCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeLateRecord(User $user, string $date, ?int $lateMinutes): void
    {
        AttendanceRecords::create([
            'user_id' => $user->id,
            'nip' => $user->nip,
            'date' => $date,
            'check_in_time' => '08:30:00',
            'status' => 'late',
            'late_minutes' => $lateMinutes,
        ]);
    }

    public function test_charges_flat_denda_per_late_occurrence_without_grace(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeLateRecord($user, '2026-07-05', 10);
        $this->makeLateRecord($user, '2026-07-10', 15);

        $context = new PayrollContext();
        (new KeterlambatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(50_000.0, $context->totalDeductions);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'keterlambatan', 'quantity' => 2, 'amount' => 50000]);
    }

    public function test_grace_count_exempts_first_n_occurrences(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_grace_count', 'module' => 'payroll', 'type' => 'integer', 'value' => '1', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeLateRecord($user, '2026-07-05', 10);
        $this->makeLateRecord($user, '2026-07-10', 15);
        $this->makeLateRecord($user, '2026-07-15', 20);

        $context = new PayrollContext();
        (new KeterlambatanCalculator())->calculate($user, $payroll, $period, $context);

        // 3 kejadian, lolos grace 1 - denda kena ke SEMUA 3 kejadian (bukan cuma kelebihannya)
        $this->assertSame(75_000.0, $context->totalDeductions);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'quantity' => 3]);
    }

    public function test_late_count_within_grace_is_not_charged(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_grace_count', 'module' => 'payroll', 'type' => 'integer', 'value' => '2', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeLateRecord($user, '2026-07-05', 10);
        $this->makeLateRecord($user, '2026-07-10', 15);

        $context = new PayrollContext();
        (new KeterlambatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id]);
    }

    public function test_any_late_minutes_above_zero_counts_no_separate_threshold(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        // Toleransi sudah selesai di level jadwal (status='late' cuma di-set kalau sudah lewat
        // toleransi) - di sini tidak ada threshold menit kedua, 1 menit pun kena hitung.
        $this->makeLateRecord($user, '2026-07-05', 1);

        $context = new PayrollContext();
        (new KeterlambatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(25_000.0, $context->totalDeductions);
    }

    public function test_uses_attendance_period_window_not_literal_payroll_period_dates(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_start_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '21', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_end_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '20', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-05-01', 'end_date' => '2026-05-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeLateRecord($user, '2026-04-15', 10); // sebelum window - JANGAN kehitung
        $this->makeLateRecord($user, '2026-05-14', 10); // di dalam window 21 Apr - 20 Mei - HARUS kehitung
        $this->makeLateRecord($user, '2026-05-25', 10); // setelah window (window salah lama: Mei21-Jun20) - JANGAN kehitung

        $context = new PayrollContext();
        (new KeterlambatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(25_000.0, $context->totalDeductions);
    }

    public function test_date_covered_by_approved_izin_terlambat_masuk_is_not_charged(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeLateRecord($user, '2026-07-05', 10); // ditutup izin - JANGAN kena denda
        $this->makeLateRecord($user, '2026-07-10', 15); // tidak ada izin - HARUS kena denda

        $category = PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value;
        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestPermissionDetails::create(['request_id' => $request->id, 'category' => $category, 'date' => '2026-07-05']);

        $context = new PayrollContext();
        (new KeterlambatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(25_000.0, $context->totalDeductions);
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'keterlambatan', 'quantity' => 1, 'amount' => 25000]);
    }

    public function test_no_denda_setting_skips_entirely(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->makeLateRecord($user, '2026-07-05', 30);

        $context = new PayrollContext();
        (new KeterlambatanCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
    }
}
