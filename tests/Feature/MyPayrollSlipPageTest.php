<?php

namespace Tests\Feature;

use App\Livewire\Keuangan\MyPayrollSlipPage;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman self-service slip gaji (tab "Penggajian" di Data Diri) — cuma boleh diakses pemilik
 * payroll-nya sendiri DAN payroll itu harus sudah berstatus dibayar.
 */
class MyPayrollSlipPageTest extends TestCase
{
    use RefreshDatabase;

    private function makePayroll(User $user, bool $isPaid): Payroll
    {
        $period = PayrollPeriod::create([
            'company_id' => $user->company_id,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ]);

        return Payroll::create([
            'user_id' => $user->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 5000000,
            'total_deductions' => 500000,
            'net_salary' => 4500000,
            'is_paid' => $isPaid,
        ]);
    }

    public function test_owner_can_view_own_paid_payroll_slip(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $payroll = $this->makePayroll($user, true);

        $this->actingAs($user);

        Livewire::test(MyPayrollSlipPage::class, ['payroll' => $payroll])
            ->assertOk()
            ->assertSee('Rp4.500.000');
    }

    public function test_forbidden_for_other_users_payroll(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $otherUser = User::factory()->create(['company_id' => $company->id]);
        $payroll = $this->makePayroll($owner, true);

        $this->actingAs($otherUser);

        Livewire::test(MyPayrollSlipPage::class, ['payroll' => $payroll])
            ->assertForbidden();
    }

    public function test_forbidden_when_not_yet_paid(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $payroll = $this->makePayroll($user, false);

        $this->actingAs($user);

        Livewire::test(MyPayrollSlipPage::class, ['payroll' => $payroll])
            ->assertForbidden();
    }
}
