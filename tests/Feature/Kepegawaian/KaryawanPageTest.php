<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Kepegawaian\KaryawanPage;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KaryawanPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsKepegawaian(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kepegawaian Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(string $code = 'TST'): Companies
    {
        return Companies::create(['code' => $code, 'name' => "Company {$code}", 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(KaryawanPage::class)->assertForbidden();
    }

    public function test_lists_only_employees_from_own_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');

        $hc = $this->actingAsKepegawaian($companyA->id);

        $sameCompanyEmployee = User::factory()->create(['company_id' => $companyA->id, 'name' => 'Karyawan Sama Perusahaan']);
        $otherCompanyEmployee = User::factory()->create(['company_id' => $companyB->id, 'name' => 'Karyawan Beda Perusahaan']);

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->assertSee('Karyawan Sama Perusahaan')
            ->assertDontSee('Karyawan Beda Perusahaan');

        $this->assertNotEquals($sameCompanyEmployee->company_id, $otherCompanyEmployee->company_id);
    }

    public function test_search_filters_by_name(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        User::factory()->create(['company_id' => $company->id, 'name' => 'Citra Dewi']);

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Dewi');
    }

    public function test_default_status_shows_only_active_employees(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $active = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Aktif']);
        $inactive = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Nonaktif']);
        $inactive->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->assertSet('status', 'aktif')
            ->assertSee('Karyawan Aktif')
            ->assertDontSee('Karyawan Nonaktif');
    }

    public function test_switching_to_inactive_status_shows_deactivated_employees(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $active = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Aktif']);
        $inactive = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Nonaktif']);
        $inactive->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->assertSee('Karyawan Nonaktif')
            ->assertDontSee('Karyawan Aktif');
    }

    public function test_restore_reactivates_employee(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->call('restore', $employee->id);

        $this->assertFalse($employee->fresh()->trashed());
    }

    public function test_cannot_restore_employee_from_other_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');

        $hc = $this->actingAsKepegawaian($companyA->id);

        $employee = User::factory()->create(['company_id' => $companyB->id]);
        $employee->delete();

        $this->actingAs($hc);

        Livewire::test(KaryawanPage::class)
            ->call('setStatus', 'tidak_aktif')
            ->call('restore', $employee->id)
            ->assertForbidden();
    }
}
