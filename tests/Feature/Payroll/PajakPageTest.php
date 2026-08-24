<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Keuangan\PajakPage;
use App\Models\Master\Companies;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Halaman "Pajak (%)" - potongan 3 kolom Gaji Pokok dari PayrollSlipRekapExport. */
class PajakPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::LIHAT_PAJAK->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Pajak Test', 'guard_name' => 'web']);
        $role->givePermissionTo([\App\Enum\CustomPermissionEnum::LIHAT_PAJAK->value]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(PajakPage::class)
            ->assertForbidden();
    }

    public function test_shows_gaji_pokok_breakdown_for_selected_period(): void
    {
        $finance = $this->actingAsFinance();

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '10', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_000_000]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true, 'name' => 'Karyawan Pajak']);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id]);
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'amount' => 3_300_000,
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);

        $component = Livewire::actingAs($finance)
            ->test(PajakPage::class)
            ->set('companyId', $company->id)
            ->set('periodId', $period->id);

        $payrolls = $component->get('payrolls');

        $this->assertCount(1, $payrolls);
        $this->assertSame($payroll->id, $payrolls->first()->id);
    }

    public function test_export_requires_period_selected(): void
    {
        $finance = $this->actingAsFinance();

        Livewire::actingAs($finance)
            ->test(PajakPage::class)
            ->call('exportExcel')
            ->assertStatus(400);
    }
}
