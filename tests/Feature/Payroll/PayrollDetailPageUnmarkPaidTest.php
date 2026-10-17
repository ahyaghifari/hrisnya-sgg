<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Padanan portal dari aksi "cancelPaid" admin panel (lihat PayrollCancelPaidTest) — supaya
 * payroll yang terlanjur ditandai dibayar bisa dibuka lagi buat diubah/di-regenerate. Alasan
 * wajib diisi (modal, bukan wire:confirm biasa) dan tercatat di audit_logs.
 */
class PayrollDetailPageUnmarkPaidTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Unmark Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value, 'Update:Payroll']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makePaidPayroll(string $startDate = '2026-07-01', string $endDate = '2026-07-31'): Payroll
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => $startDate, 'end_date' => $endDate, 'processed_at' => now()]);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Karyawan']);

        return Payroll::create(['user_id' => $employee->id, 'employee_id' => $employee->employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'is_paid' => true]);
    }

    public function test_unmark_as_paid_reopens_payroll_and_records_audit_log_with_reason(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePaidPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->set('cancelPaidReason', 'Salah tandai dibayar, seharusnya belum')
            ->call('unmarkAsPaid');

        $this->assertFalse((bool) $payroll->fresh()->is_paid);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'payroll_payment',
            'key' => 'payroll:' . $payroll->id,
            'user_id' => $finance->id,
            'old_value' => 'Sudah Dibayar',
            'new_value' => 'Dibatalkan',
        ]);
    }

    public function test_unmark_as_paid_requires_reason(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePaidPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->set('cancelPaidReason', '')
            ->call('unmarkAsPaid')
            ->assertHasErrors(['cancelPaidReason' => 'required']);

        $this->assertTrue((bool) $payroll->fresh()->is_paid);
    }

    public function test_unmark_as_paid_forbidden_without_update_permission(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Unmark Terbatas', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_KEUANGAN->value]);
        $viewer = User::factory()->create();
        $viewer->assignRole($role);

        $payroll = $this->makePaidPayroll();

        Livewire::actingAs($viewer)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->set('cancelPaidReason', 'Coba akses')
            ->call('unmarkAsPaid')
            ->assertForbidden();

        $this->assertTrue((bool) $payroll->fresh()->is_paid);
    }

    /** Periode Juli sudah bukan periode terbaru company ini (ada periode Agustus) — regenerate
     * ditutup walau payroll-nya sendiri sudah dibuka lagi dari status dibayar. */
    public function test_regenerate_forbidden_when_a_newer_period_exists(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePaidPayroll('2026-07-01', '2026-07-31');

        PayrollPeriod::create([
            'company_id' => $payroll->payrollPeriod->company_id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'processed_at' => now(),
        ]);

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->set('cancelPaidReason', 'Koreksi periode lampau')
            ->call('unmarkAsPaid')
            ->call('regeneratePayroll')
            ->assertForbidden();

        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id]);
    }

    /** Payroll di periode TERBARU company itu (belum ada periode setelahnya) tetap bisa
     * di-regenerate seperti biasa setelah dibuka lagi dari status dibayar. */
    public function test_regenerate_still_allowed_when_period_is_the_latest(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePaidPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->set('cancelPaidReason', 'Koreksi nominal')
            ->call('unmarkAsPaid')
            ->call('regeneratePayroll')
            ->assertRedirect();

        $this->assertDatabaseMissing('payrolls', ['id' => $payroll->id]);
    }

    /** Modal "Riwayat Pembayaran" (portal) bacaannya dari AuditLog yang sama dengan section
     * "Riwayat Pembayaran" di admin panel (PayrollInfolist) - alasan pembatalan ikut kebaca lewat
     * AuditLog::reasonFromLabel(). */
    public function test_payment_history_shows_entries_with_reason(): void
    {
        $finance = $this->actingAsFinance();
        $payroll = $this->makePaidPayroll();

        Livewire::actingAs($finance)
            ->test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->set('cancelPaidReason', 'Salah tandai dibayar')
            ->call('unmarkAsPaid')
            ->call('openPaymentHistoryModal')
            ->assertSet('showPaymentHistoryModal', true);

        $entry = \App\Models\AuditLog::where('key', 'payroll:' . $payroll->id)
            ->where('module', 'payroll_payment')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('Salah tandai dibayar', $entry->reasonFromLabel());
    }
}
