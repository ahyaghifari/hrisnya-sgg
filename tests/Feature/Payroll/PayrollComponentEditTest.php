<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\PayrollDetailPage;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tombol "Ubah" per komponen di halaman detail payroll portal (permintaan user: bisa edit
 * komponen APA SAJA langsung, bukan cuma manual line Bon) - digerbangi permission KHUSUS
 * `edit_payroll_adjustment` (App\Enum\CustomPermissionEnum::EDIT_PAYROLL_ADJUSTMENT), terpisah
 * dari `Update:Payroll` biasa (lihat getCanEditAdjustmentProperty()).
 */
class PayrollComponentEditTest extends TestCase
{
    use RefreshDatabase;

    private function makePayrollWithGajiPokok(Companies $company): Payroll
    {
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => now(), 'end_date' => now(), 'payment_date' => now()]);
        $user = User::create(['name' => 'Karyawan Uji', 'email' => 'karyawan@test.local', 'password' => bcrypt('secret'), 'nip' => '00123', 'company_id' => $company->id, 'is_active' => true]);

        $payroll = Payroll::create([
            'employee_id' => $user->employee->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 5_000_000,
            'total_deductions' => 0,
            'net_salary' => 5_000_000,
            'is_paid' => false,
        ]);

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'quantity' => 1,
            'amount' => 5_000_000,
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);

        return $payroll->fresh(['payrollPeriod', 'employee']);
    }

    private function actingAsUserWithPermissions(Companies $company, array $permissions): User
    {
        $user = User::create(['name' => 'Actor', 'email' => 'actor@test.local', 'password' => bcrypt('secret'), 'company_id' => $company->id, 'is_active' => true]);

        $role = Role::firstOrCreate(['name' => 'Payroll Adjustment Test Role ' . uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    public function test_edit_component_with_permission_updates_amount_and_totals(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        $this->actingAsUserWithPermissions($company, ['View:Payroll', 'Update:Payroll', 'edit_payroll_adjustment']);
        $payroll = $this->makePayrollWithGajiPokok($company);

        Livewire::test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openComponentEditModal', PayrollComponentEnum::GAJI_POKOK->value)
            ->assertSet('componentEditAmount', '5000000')
            ->set('componentEditAmount', '6000000')
            ->set('componentEditNote', 'Koreksi kesalahan input pangkat')
            ->call('saveComponentEdit')
            ->assertHasNoErrors()
            ->assertSet('showComponentEditModal', false);

        $payroll->refresh();
        $this->assertSame(6_000_000.0, (float) $payroll->gross_salary);
        $this->assertSame(6_000_000.0, (float) $payroll->items()->where('source_type', 'gaji_pokok')->first()->amount);
    }

    public function test_edit_component_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        // Cuma View+Update:Payroll biasa, TANPA edit_payroll_adjustment.
        $this->actingAsUserWithPermissions($company, ['View:Payroll', 'Update:Payroll']);
        $payroll = $this->makePayrollWithGajiPokok($company);

        Livewire::test(PayrollDetailPage::class, ['payroll' => $payroll])
            ->call('openComponentEditModal', PayrollComponentEnum::GAJI_POKOK->value)
            ->assertForbidden();
    }

    public function test_edit_component_on_paid_payroll_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        $this->actingAsUserWithPermissions($company, ['View:Payroll', 'Update:Payroll', 'edit_payroll_adjustment']);
        $payroll = $this->makePayrollWithGajiPokok($company);
        $payroll->update(['is_paid' => true]);

        Livewire::test(PayrollDetailPage::class, ['payroll' => $payroll->fresh(['payrollPeriod', 'employee'])])
            ->call('openComponentEditModal', PayrollComponentEnum::GAJI_POKOK->value)
            ->assertForbidden();
    }
}
