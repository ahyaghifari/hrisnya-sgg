<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * config('attendance.excluded_nips') (mis. Direktur/Super Admin) harus hilang total dari rekap
 * presensi - bukan cuma tidak wajib presensi, tapi tidak ikut ditampilkan/dihitung sama sekali
 * (lihat RekapPresensiPage::baseUserQuery()).
 */
class RekapPresensiPageExcludedNipTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Rekap Presensi Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    public function test_excluded_nip_is_hidden_from_daily_rows_and_stats(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);

        $director = User::factory()->create(['company_id' => $company->id, 'name' => 'Direktur Utama', 'nip' => '00001']);
        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Staff Biasa', 'nip' => '00002']);

        Config::set('attendance.excluded_nips', ['00001']);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->assertSee($staff->name)
            ->assertDontSee($director->name)
            ->assertSet('todayStats.total_employees', 2); // viewer + staff, direktur tidak ikut kehitung
    }

    public function test_no_excluded_nips_configured_shows_everyone(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);

        $director = User::factory()->create(['company_id' => $company->id, 'name' => 'Direktur Utama', 'nip' => '00001']);

        Config::set('attendance.excluded_nips', []);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->assertSee($director->name);
    }
}
