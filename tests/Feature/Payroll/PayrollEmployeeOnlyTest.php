<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Payroll berkunci employee_id sepenuhnya: baris baru tanpa user_id valid, dan nama/NIP dibaca dari employee. */
class PayrollEmployeeOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_row_without_user_id_is_valid_and_reads_identity_from_employee(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        $this->assertNull($payroll->fresh()->getRawOriginal('user_id'));
        $this->assertSame($user->employee->nama_lengkap, $payroll->fresh('employee')->employee->nama_lengkap);
        $this->assertFalse(method_exists($payroll, 'user'), 'relasi user() sudah dicabut dari Payroll');
    }
}