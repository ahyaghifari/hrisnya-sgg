<?php

namespace Tests\Feature\Payroll;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Enum\AttendanceReqStatus;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\CutiCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CutiCalculator - Cuti Melahirkan/Keguguran (instruksi user 2026-09-05): SEMUA hari cutinya
 * dipotong, dua teknik (setting `cuti_melahirkan_metode`). Test ini panggil Calculator langsung
 * (pola sama GajiPokokCalculatorTest) - $context di-set manual buat simulasi "komponen lain
 * sudah jalan duluan", bukan lewat PayrollService::generatePayroll() penuh.
 */
class CutiMelahirkanCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
    }

    private function setDivisor(int $days): void
    {
        Setting::create(['company_id' => $this->company->id, 'key' => 'prorate_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'hari_kerja_efektif', 'label' => 'Metode Pembagi Prorate']);
        Setting::create(['company_id' => $this->company->id, 'key' => 'kepegawaian_hari_kerja_efektif_per_bulan', 'module' => 'kepegawaian', 'type' => 'integer', 'value' => (string) $days, 'label' => 'Hari Kerja Efektif per Bulan']);
    }

    private function setMetode(string $metode): void
    {
        Setting::create(['company_id' => $this->company->id, 'key' => 'cuti_melahirkan_metode', 'module' => 'payroll', 'type' => 'string', 'value' => $metode, 'label' => 'Metode Potongan Cuti Melahirkan/Keguguran']);
    }

    /** Pangkat/Kelas/PangkatSalary/EmployeeType Tetap - dasar buat GajiPokokCalculator::resolveAmount(). */
    private function employeeWithGajiPokok(float $baseSalary, float $kenaikanPersen): User
    {
        Setting::create(['company_id' => $this->company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => (string) $kenaikanPersen, 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas = Kelas::create(['name' => 'Kelas I', 'company_id' => $this->company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $this->company->id, 'start_kelas_id' => $kelas->id, 'max_kelas_id' => $kelas->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => $baseSalary]);
        $tetap = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $this->company->id, 'category' => 'tetap']);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas->id, 'employee_type_id' => $tetap->id, 'gender' => 'perempuan']);

        return $user;
    }

    /** Approved leave request kategori Melahirkan, tanggal apa adanya (boleh melebihi periode -
     *  CutiCalculator yang motong ke irisan periode). */
    private function approvedMaternityLeave(User $user, string $start, string $end): void
    {
        $type = LeaveTypeCategoryEnum::MELAHIRKAN;

        $request = AttendanceRequests::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'type' => 'leave', 'status' => AttendanceReqStatus::APPROVED->value,
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id, 'category' => $type->value, 'leave_type' => $type->label(),
            'start_date' => $start, 'end_date' => $end,
            'total_days' => \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1,
        ]);
    }

    /**
     * Sama persis contoh angka user: THP 3.000.000, Gaji Pokok Dasar 1.600.000 + kenaikan 8% =
     * 1.728.000, E=26, L=14 (12 hari kerja + 14 hari cuti = 26). Potongan = THP - (THP/26*12 +
     * 1.728.000/26*14) = 3.000.000 - 2.315.076,92 = 684.923,08 (rounding akhir 2 desimal, BEDA
     * TIPIS dari contoh user "684.924" yang membulatkan tiap langkah - bukan bug, cuma beda
     * urutan pembulatan).
     */
    public function test_thp_method_matches_worked_example(): void
    {
        $this->setDivisor(26);
        $this->setMetode('thp');
        $user = $this->employeeWithGajiPokok(1_600_000, 8);

        $period = PayrollPeriod::create(['company_id' => $this->company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        // 14 hari cuti DALAM periode ini (irisan) - tanggal dibikin persis di dalam periode.
        $this->approvedMaternityLeave($user, '2026-07-01', '2026-07-14');

        $context = new PayrollContext();
        $context->grossSalary = 3_000_000; // simulasi THP sebelum potongan cuti = 3.000.000
        $context->totalDeductions = 0;

        (new CutiCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertEqualsWithDelta(684_923.08, $context->totalDeductions, 0.02);
        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => PayrollComponentEnum::CUTI->value,
        ]);
    }

    /** Cuti menutupi SATU PERIODE PENUH (L >= E): porsi THP = 0, Gaji Pokok dibayar PENUH
     *  (rasio dikunci maks 1) - potongan = THP - GajiPokokAkhir persis. */
    public function test_thp_method_full_period_leave_keeps_only_gaji_pokok(): void
    {
        $this->setDivisor(26);
        $this->setMetode('thp');
        $user = $this->employeeWithGajiPokok(1_600_000, 8); // Gaji Pokok Akhir = 1.728.000

        $period = PayrollPeriod::create(['company_id' => $this->company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        // 31 hari cuti (periode penuh) - jauh melebihi E=26.
        $this->approvedMaternityLeave($user, '2026-07-01', '2026-07-31');

        $context = new PayrollContext();
        $context->grossSalary = 3_000_000;
        $context->totalDeductions = 0;

        (new CutiCalculator())->calculate($user, $payroll, $period, $context);

        // Potongan = 3.000.000 - 1.728.000 = 1.272.000 -> yang tersisa cuma Gaji Pokok Akhir.
        $this->assertEqualsWithDelta(1_272_000.0, $context->totalDeductions, 0.01);
    }

    /**
     * Teknik "per_komponen": Tunjangan Jabatan (diprorate) & Lembur (TIDAK diprorate, basis
     * submission aktual) sudah tergenerate duluan sebagai payroll_items. E=26, L=14 -> rasio
     * (26-14)/26 = 12/26, potongan = Tunjangan x (1 - 12/26) = Tunjangan x 14/26. Lembur tidak
     * ikut kepotong sama sekali.
     */
    public function test_per_komponen_method_prorates_only_selected_components(): void
    {
        $this->setDivisor(26);
        $this->setMetode('per_komponen');
        $user = $this->employeeWithGajiPokok(1_600_000, 8);

        $period = PayrollPeriod::create(['company_id' => $this->company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        PayrollItem::create([
            'payroll_id' => $payroll->id, 'name' => 'Tunjangan Jabatan', 'type' => 'earning',
            'quantity' => 1, 'amount' => 1_000_000, 'source_type' => PayrollComponentEnum::TUNJANGAN_JABATAN->value,
        ]);
        PayrollItem::create([
            'payroll_id' => $payroll->id, 'name' => 'Lembur', 'type' => 'earning',
            'quantity' => 1, 'amount' => 500_000, 'source_type' => PayrollComponentEnum::LEMBUR->value,
        ]);

        $this->approvedMaternityLeave($user, '2026-07-01', '2026-07-14');

        $context = new PayrollContext();
        $context->grossSalary = 1_500_000;
        $context->totalDeductions = 0;

        (new CutiCalculator())->calculate($user, $payroll, $period, $context);

        // 1.000.000 x 14/26 = 538.461,54 - Lembur (500.000) tidak ikut dipotong sama sekali.
        $this->assertEqualsWithDelta(1_000_000 * 14 / 26, $context->totalDeductions, 0.01);
    }

    /** Tidak ada cuti melahirkan/keguguran yang overlap periode -> no-op, sama seperti sebelumnya
     *  (Cuti Tahunan/Haid tidak dipotong sama sekali). */
    public function test_no_op_when_no_maternity_leave_overlaps_period(): void
    {
        $this->setDivisor(26);
        $this->setMetode('thp');
        $user = $this->employeeWithGajiPokok(1_600_000, 8);

        $period = PayrollPeriod::create(['company_id' => $this->company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);

        $context = new PayrollContext();
        $context->grossSalary = 3_000_000;

        (new CutiCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertSame(0.0, $context->totalDeductions);
        $this->assertDatabaseMissing('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => PayrollComponentEnum::CUTI->value,
        ]);
    }
}
