<?php

namespace Tests\Feature\Payroll;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\PayrollPeriodsPage;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Versi portal dari PayrollPeriodResource (/admin/payroll-periods), reuse Table/Form/Infolist
 * Filament yang sama pola IncentivesPage. `->assertSee()` (bukan cuma `assertOk()`) dipakai
 * sengaja — Filament Table class yang dirancang buat jalan di dalam Resource butuh ->query()
 * eksplisit kalau dipakai standalone di Livewire polos, dan assertOk() saja TIDAK menangkap
 * baris itu hilang (baru ketahuan lewat request HTTP asli / ->html(), lihat riwayat sesi ini).
 */
class PayrollPeriodsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFinance(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEUANGAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Keuangan Periode Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEUANGAN->value);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PayrollPeriodsPage::class)
            ->assertForbidden();
    }

    public function test_table_renders_existing_periods(): void
    {
        $finance = $this->actingAsFinance();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        Livewire::actingAs($finance)
            ->test(PayrollPeriodsPage::class)
            ->assertOk()
            ->assertSee('Test Company');
    }
}
