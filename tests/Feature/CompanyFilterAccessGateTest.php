<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Models\CompanyFilterAccess;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * User::canFilterByCompanyForSystem() - lapisan tambahan di atas permission
 * lihat_perusahaan_lain (App\Enum\CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN), fail-closed:
 * default hidden sampai admin bikin row CompanyFilterAccess yang mengizinkan sistem tsb.
 * Lihat knowledge/markdown/permission-lihat-perusahaan-lain-dan-filter-perusahaan.md.
 */
class CompanyFilterAccessGateTest extends TestCase
{
    use RefreshDatabase;

    private function userWithLihatPerusahaanLain(Companies $company): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Lihat Perusahaan Lain Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_tanpa_permission_dasar_selalu_false(): void
    {
        $company = Companies::create(['code' => 'CFA'.rand(1000,9999), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->assertFalse($user->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEUANGAN));
    }

    public function test_punya_permission_tapi_tanpa_row_config_default_false(): void
    {
        $company = Companies::create(['code' => 'CFA'.rand(1000,9999), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = $this->userWithLihatPerusahaanLain($company);

        $this->assertFalse($user->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEUANGAN));
    }

    public function test_row_role_match_dengan_sistem_dicentang_true(): void
    {
        $company = Companies::create(['code' => 'CFA'.rand(1000,9999), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = $this->userWithLihatPerusahaanLain($company);
        $roleId = $user->roles->first()->id;

        CompanyFilterAccess::create([
            'company_id' => $company->id,
            'grantee_type' => 'role',
            'grantee_id' => (string) $roleId,
            'systems' => [CustomPermissionEnum::SISTEM_KEUANGAN->value],
        ]);

        $this->assertTrue($user->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEUANGAN));
    }

    public function test_row_ada_tapi_sistem_tidak_dicentang_false(): void
    {
        $company = Companies::create(['code' => 'CFA'.rand(1000,9999), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = $this->userWithLihatPerusahaanLain($company);
        $roleId = $user->roles->first()->id;

        CompanyFilterAccess::create([
            'company_id' => $company->id,
            'grantee_type' => 'role',
            'grantee_id' => (string) $roleId,
            'systems' => [CustomPermissionEnum::SISTEM_ABSENSI->value],
        ]);

        $this->assertFalse($user->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEUANGAN));
    }

    public function test_row_user_type_match_true(): void
    {
        $company = Companies::create(['code' => 'CFA'.rand(1000,9999), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = $this->userWithLihatPerusahaanLain($company);

        CompanyFilterAccess::create([
            'company_id' => $company->id,
            'grantee_type' => 'user',
            'grantee_id' => $user->id,
            'systems' => [CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value],
        ]);

        $this->assertTrue($user->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEPEGAWAIAN));
    }
}
