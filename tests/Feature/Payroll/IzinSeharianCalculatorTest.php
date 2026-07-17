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
}
