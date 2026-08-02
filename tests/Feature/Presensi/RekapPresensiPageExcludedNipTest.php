<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * User::NON_KARYAWAN_IDS (Direktur/Super Admin) dibuang lewat global scope 'non_karyawan' -
 * jadi tidak pernah muncul di daftar/rekap karyawan manapun, termasuk rekap presensi (lihat
 * User::boot()).
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

    public function test_non_karyawan_account_is_hidden_from_daily_rows_and_stats(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);

        $director = User::factory()->create(['company_id' => $company->id, 'name' => 'Direktur Utama']);
        DB::table('users')->where('id', $director->id)->update(['id' => User::NON_KARYAWAN_IDS[0]]);

        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Staff Biasa']);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->assertSee($staff->name)
            ->assertDontSee($director->name)
            ->assertSet('todayStats.total_employees', 2); // viewer + staff, direktur tidak ikut kehitung
    }
}
