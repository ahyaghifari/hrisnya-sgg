<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Jobs\SendPayrollSlipJob;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Livewire\Keuangan\PayrollsPage;
use App\Mail\PayrollSlipMail;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kirim slip gaji ke email: PayrollDetailPage (satu payroll) & PayrollsPage (ceklis massal)
 * cuma dispatch App\Jobs\SendPayrollSlipJob (queue) — job itu sendiri yang bangun PDF terenkripsi
 * (App\Services\Payroll\PayrollService::encryptedSlip()) dan kirim App\Mail\PayrollSlipMail.
 */
class PayrollSlipEmailTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Slip Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, 'Update:Payroll']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @return array{0: Companies, 1: PayrollPeriod, 2: Payroll} */
    private function makePaidPayroll(array $employeeAttrs = [], array $profileAttrs = []): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(array_merge(['company_id' => $company->id, 'nip' => '20260001123'], $employeeAttrs));
        UserProfiles::create(array_merge(['user_id' => $employee->id, 'date_of_birth' => '1995-05-17'], $profileAttrs));
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-06-01', 'end_date' => '2026-06-30']);
        $payroll = Payroll::create([
            'user_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 1000000,
            'net_salary' => 1000000,
            'is_paid' => true,
        ]);

        return [$company, $period, $payroll];
    }

    // ── PayrollService ───────────────────────────────────────────────────────

    public function test_slip_password_is_birth_day_plus_last_3_nip_digits(): void
    {
        [, , $payroll] = $this->makePaidPayroll(['nip' => '20260001123'], ['date_of_birth' => '1995-05-07']);

        $password = app(PayrollService::class)->slipPassword($payroll->user);

        $this->assertSame('07123', $password);
    }

    public function test_slip_password_is_null_without_birth_date_or_nip(): void
    {
        $employee = User::factory()->create(['nip' => '20260001123']);
        UserProfiles::create(['user_id' => $employee->id, 'date_of_birth' => null]);

        $this->assertNull(app(PayrollService::class)->slipPassword($employee));
    }

    public function test_encrypted_slip_throws_without_password_data(): void
    {
        [, , $payroll] = $this->makePaidPayroll([], ['date_of_birth' => null]);

        $this->expectException(\RuntimeException::class);
        app(PayrollService::class)->encryptedSlip($payroll);
    }

    public function test_encrypted_slip_produces_pdf_binary(): void
    {
        [, , $payroll] = $this->makePaidPayroll();

        $slip = app(PayrollService::class)->encryptedSlip($payroll->load('payrollPeriod', 'user.profile'));

        $this->assertSame('17123', $slip['password']);
        $this->assertStringStartsWith('%PDF', $slip['binary']);
    }

    // ── Job ───────────────────────────────────────────────────────────────

    public function test_job_sends_mail_and_stamps_slip_sent_at(): void
    {
        Mail::fake();
        [, , $payroll] = $this->makePaidPayroll();
        $sender = User::factory()->create(['name' => 'Noor Afini']);

        (new SendPayrollSlipJob($payroll->id, $sender->id))->handle(app(PayrollService::class));

        Mail::assertSent(PayrollSlipMail::class, fn (PayrollSlipMail $mail) => $mail->hasTo($payroll->user->email) && $mail->senderName === 'Noor Afini');
        $this->assertNotNull($payroll->fresh()->slip_sent_at);
    }

    public function test_job_skips_unpaid_payroll(): void
    {
        Mail::fake();
        [, , $payroll] = $this->makePaidPayroll();
        $payroll->update(['is_paid' => false]);
        $sender = User::factory()->create();

        (new SendPayrollSlipJob($payroll->id, $sender->id))->handle(app(PayrollService::class));

        Mail::assertNothingSent();
        $this->assertNull($payroll->fresh()->slip_sent_at);
    }

    // ── PayrollDetailPage ────────────────────────────────────────────────────

    public function test_detail_page_forbids_sending_when_not_paid(): void
    {
        $finance = $this->actingAsFinance();
        [, , $payroll] = $this->makePaidPayroll();
        $payroll->update(['is_paid' => false]);

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('sendSlipEmail')
            ->assertForbidden();
    }

    public function test_detail_page_dispatches_job_when_paid(): void
    {
        Queue::fake();
        $finance = $this->actingAsFinance();
        [, , $payroll] = $this->makePaidPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('sendSlipEmail');

        Queue::assertPushed(SendPayrollSlipJob::class, fn ($job) => $job->payrollId === $payroll->id && $job->senderUserId === $finance->id);
    }

    // ── PayrollsPage (ceklis massal) ─────────────────────────────────────────

    public function test_mark_selected_as_paid(): void
    {
        $finance = $this->actingAsFinance();
        [$company, $period, $payroll] = $this->makePaidPayroll();
        $payroll->update(['is_paid' => false]);

        Livewire::actingAs($finance)
            ->test(PayrollsPage::class)
            ->set('companyId', $company->id)
            ->set('selected', [$payroll->id])
            ->call('markSelectedAsPaid');

        $this->assertTrue((bool) $payroll->fresh()->is_paid);
    }

    public function test_send_slips_to_selected_forbidden_when_not_all_paid(): void
    {
        Queue::fake();
        $finance = $this->actingAsFinance();
        [$company, , $payroll] = $this->makePaidPayroll();
        $payroll->update(['is_paid' => false]);

        Livewire::actingAs($finance)
            ->test(PayrollsPage::class)
            ->set('companyId', $company->id)
            ->set('selected', [$payroll->id])
            ->call('sendSlipsToSelected')
            ->assertForbidden();

        Queue::assertNotPushed(SendPayrollSlipJob::class);
    }

    public function test_send_slips_to_selected_dispatches_job_per_employee(): void
    {
        Queue::fake();
        $finance = $this->actingAsFinance();
        [$company, , $payroll] = $this->makePaidPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollsPage::class)
            ->set('companyId', $company->id)
            ->set('selected', [$payroll->id])
            ->call('sendSlipsToSelected');

        Queue::assertPushed(SendPayrollSlipJob::class, fn ($job) => $job->payrollId === $payroll->id);
    }
}
