<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Livewire\Keuangan\PayrollsPage;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Notifications\PayrollPaid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Notifikasi WebPush "Gaji Sudah Dibayar" dikirim SAAT tombol "Tandai Sudah Dibayar" diklik —
 * terpisah dari App\Jobs\SendPayrollSlipJob (kirim email), tidak menunggu email terkirim.
 */
class PayrollPaidNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Notif Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, 'Update:Payroll']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeUnpaidPayroll(?string $companyId = null): Payroll
    {
        $companyId ??= Companies::create(['code' => 'TST' . uniqid(), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id;
        $employee = User::factory()->create(['company_id' => $companyId]);
        $period = PayrollPeriod::create(['company_id' => $companyId, 'start_date' => '2026-06-01', 'end_date' => '2026-06-30']);

        return Payroll::create([
            'user_id' => $employee->id, 'employee_id' => $employee->employee->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 1000000,
            'net_salary' => 1000000,
        ]);
    }

    public function test_detail_page_notifies_employee_when_marked_paid(): void
    {
        Notification::fake();
        $finance = $this->actingAsFinance();
        $payroll = $this->makeUnpaidPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('markAsPaid');

        Notification::assertSentTo($payroll->user, PayrollPaid::class);
    }

    public function test_bulk_mark_paid_notifies_only_newly_paid_employees(): void
    {
        Notification::fake();
        $finance = $this->actingAsFinance();
        $company = Companies::create(['code' => 'TST' . uniqid(), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $alreadyPaid = $this->makeUnpaidPayroll($company->id);
        $alreadyPaid->update(['is_paid' => true]);
        $newlyPaid = $this->makeUnpaidPayroll($company->id);

        Livewire::actingAs($finance)
            ->test(PayrollsPage::class)
            ->set('companyId', $newlyPaid->payrollPeriod->company_id)
            ->set('selected', [$alreadyPaid->id, $newlyPaid->id])
            ->call('markSelectedAsPaid');

        Notification::assertSentTo($newlyPaid->fresh()->user, PayrollPaid::class);
        Notification::assertNotSentTo($alreadyPaid->fresh()->user, PayrollPaid::class);
    }
}
