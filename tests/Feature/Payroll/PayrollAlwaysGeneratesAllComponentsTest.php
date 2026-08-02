<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\CompanyPayrollComponent;
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
 * PayrollService::runComponent() sekarang WAJIB nyisain baris payroll_items (Rp0 kalau perlu)
 * buat SETIAP komponen yang AKTIF di company ini - lepas dari status "Penerima Komponen Gaji"
 * tipe karyawan itu (2026-08-02). PayrollComponentRecipient cuma nge-gate PERHITUNGAN/pembayaran
 * (Calculator dipanggil atau tidak), BUKAN keberadaan barisnya - tipe karyawan yang belum
 * terdaftar sebagai penerima tetap dapat baris Rp0, bukan hilang total. Test-test Calculator
 * per-unit yang sudah ada (assertDatabaseMissing) TETAP valid tanpa diubah - itu menguji
 * Calculator sendiri, bukan hasil akhir lewat orchestrator penuh (generatePayroll()) seperti
 * di sini.
 */
class PayrollAlwaysGeneratesAllComponentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndEmployeeType(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);

        return [$company, $employeeType];
    }

    public function test_cuti_no_op_still_creates_zero_row(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::CUTI->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::CUTI->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'cuti', 'amount' => 0]);
    }

    public function test_not_registered_as_recipient_still_gets_zero_row_but_not_paid(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_start_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '1', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_end_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '31', 'label' => 'x']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KETERLAMBATAN->value, 'is_active' => true]);
        // Cuma didaftarkan penerima Gaji Pokok - SENGAJA TIDAK didaftarkan penerima Keterlambatan
        // (Perilaku Orientasi di kasus nyata: tipe karyawan cuma penerima sebagian komponen).
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        // Data telat NYATA ada - tapi karena bukan penerima Keterlambatan, tidak boleh kepotong.
        AttendanceRecords::create([
            'user_id' => $user->id, 'nip' => $user->nip, 'date' => '2026-07-05',
            'check_in_time' => '08:30:00', 'status' => 'late', 'late_minutes' => 20,
        ]);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        // Baris Keterlambatan tetap ADA (Rp0) meski bukan penerima - "penerima cuma nentuin
        // dibayar atau tidak, bukan nentuin barisnya ada atau tidak".
        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'keterlambatan',
            'amount' => 0,
        ]);
    }

    public function test_gaji_pokok_without_pangkat_kelas_still_creates_zero_row(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        // Sengaja tidak isi pangkat_id/kelas_id - GajiPokokCalculator skip diam-diam tanpa itu.
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'gaji_pokok', 'amount' => 0]);
    }

    public function test_keterlambatan_zero_occurrence_row_bakes_threshold_into_name(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_threshold_menit', 'module' => 'payroll', 'type' => 'integer', 'value' => '10', 'label' => 'x']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KETERLAMBATAN->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KETERLAMBATAN->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'keterlambatan',
            'amount' => 0,
            'name' => 'Keterlambatan > 10 menit',
        ]);
    }

    public function test_keterlambatan_real_occurrence_bakes_threshold_into_name(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_denda', 'module' => 'payroll', 'type' => 'decimal', 'value' => '25000', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'keterlambatan_threshold_menit', 'module' => 'payroll', 'type' => 'integer', 'value' => '10', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_start_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '1', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'attendance_period_end_day', 'module' => 'attendance', 'type' => 'integer', 'value' => '31', 'label' => 'x']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KETERLAMBATAN->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::KETERLAMBATAN->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        AttendanceRecords::create([
            'user_id' => $user->id, 'nip' => $user->nip, 'date' => '2026-07-05',
            'check_in_time' => '08:30:00', 'status' => 'late', 'late_minutes' => 20,
        ]);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'keterlambatan',
            'amount' => 25000,
            'name' => 'Keterlambatan > 10 menit',
        ]);
    }

    public function test_lembur_zero_occurrence_creates_placeholder_row_with_threshold_label(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        Setting::create(['company_id' => $company->id, 'key' => 'lembur_minimum_jam', 'module' => 'payroll', 'type' => 'integer', 'value' => '2', 'label' => 'x']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::LEMBUR->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::LEMBUR->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'lembur',
            'amount' => 0,
            'name' => 'Lembur (Dihitung > 2 jam)',
        ]);
    }

    public function test_bpjs_kesehatan_first_month_creates_zero_row_with_note(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::BPJS_KESEHATAN->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::BPJS_KESEHATAN->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        // Join di tengah periode - syarat "bulan pertama" di BpjsKesehatanCalculator.
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id, 'joining_date' => '2026-07-15']);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $item = \App\Models\Payroll\PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'bpjs_kesehatan')->firstOrFail();
        $this->assertSame(0.0, (float) $item->amount);
        $this->assertStringContainsString('bulan pertama', $item->note);
    }

    public function test_bpjs_kesehatan_real_deduction_bakes_rate_into_name(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        Setting::create(['company_id' => null, 'key' => 'bpjs_kesehatan_rate', 'module' => 'payroll', 'type' => 'decimal', 'value' => '1', 'label' => 'x']);
        Setting::create(['company_id' => $company->id, 'key' => 'bpjs_declared_value', 'module' => 'payroll', 'type' => 'decimal', 'value' => '5000000', 'label' => 'x']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::BPJS_KESEHATAN->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::BPJS_KESEHATAN->value, 'employee_type_id' => $employeeType->id]);

        $pangkat = \App\Models\Master\Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id]);
        \App\Models\Payroll\PangkatBpjsBasis::create(['pangkat_id' => $pangkat->id, 'bpjs_basis' => 'declared_value']);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id, 'pangkat_id' => $pangkat->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'bpjs_kesehatan',
            'amount' => 50000,
            'name' => 'BPJS Kesehatan 1%',
        ]);
    }

    public function test_fee_claim_and_pajak_jasa_dokter_still_never_create_a_row_when_no_fee_items(): void
    {
        [$company, $employeeType] = $this->makeCompanyAndEmployeeType();
        // Butuh minimal 1 komponen lain aktif+eligible, kalau tidak generatePayroll() skip total
        // karyawan ini (PayrollComponentRecipient::isRegisteredAnywhere() gate).
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::CUTI->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::CUTI->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        app(PayrollService::class)->generatePayroll($period);
        $payroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'fee_claim']);
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'pajak_jasa_dokter']);
    }
}
