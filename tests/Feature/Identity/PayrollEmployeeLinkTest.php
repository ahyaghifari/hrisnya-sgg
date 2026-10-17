<?php

namespace Tests\Feature\Identity;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Identity\EmployeeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Migrasi payroll user_id -> employee_id: baris tanpa employee_id dilaporkan verify dan diperbaiki backfill. */
class PayrollEmployeeLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_reports_missing_employee_id_and_backfill_repairs_it(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => null, 'payroll_period_id' => $period->id]);

        // Baris detail selisih ("dimensi: NIP") hanya muncul kalau ada yang menyimpang.
        Artisan::call('identity:verify');
        $this->assertStringContainsString('payroll: payrolls.employee_id:', Artisan::output());

        $this->assertSame(1, app(EmployeeSyncService::class)->linkPayrollEmployeeIds());
        $this->assertSame($user->employee->id, $payroll->fresh()->employee_id);

        Artisan::call('identity:verify');
        $this->assertStringNotContainsString('payroll: payrolls.employee_id:', Artisan::output());
    }
}
