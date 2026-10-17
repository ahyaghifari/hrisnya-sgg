<?php

namespace Tests\Feature\Payroll;

use App\Livewire\Keuangan\PayrollDetailPage;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Sama seperti PayrollsPageDecryptFallbackTest, versi halaman Detail Payroll - baris
 * payroll_items yang gagal didekripsi tidak boleh bikin halaman ini crash total. */
class PayrollDetailPageDecryptFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        foreach (['ViewAny:Payroll', 'View:Payroll'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'Payroll Detail Admin Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:Payroll', 'View:Payroll']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_broken_item_amount_does_not_crash_page(): void
    {
        $admin = $this->actingAsAdmin();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Karyawan']);
        $payroll = Payroll::create(['employee_id' => $employee->employee->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5000000, 'net_salary' => 4500000, 'total_deductions' => 500000]);

        $item = PayrollItem::create(['payroll_id' => $payroll->id, 'name' => 'Gaji Pokok', 'type' => 'earning', 'quantity' => 1, 'amount' => 5000000, 'source_type' => 'gaji_pokok']);
        // Simulasi baris lama yang masih plaintext - bypass cast lewat query builder.
        DB::table('payroll_items')->where('id', $item->id)->update(['amount' => '5000000']);

        $component = Livewire::actingAs($admin)->test(PayrollDetailPage::class, ['payroll' => $payroll]);

        $component->assertOk();
        $component->assertSee('Budi Karyawan');
        $component->assertSee('gagal dibaca');
        $component->assertSet('decryptFailed', true);
    }
}
