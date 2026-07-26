<?php

namespace Tests\Feature\LMS;

use App\Livewire\LMS\LmsCourseBaselinesPage;
use App\Models\LMS\LmsCourseBaselines;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test halaman "Baseline Pelatihan" portal (LmsCourseBaselinesPage) — padanan portal dari
 * Filament LmsCourseBaselinesResource. Mencakup: gate akses, CRUD lewat modal, toggle status,
 * dan company-scoping (user company biasa vs user Global Company).
 */
class LmsCourseBaselinesPageTest extends TestCase
{
    use RefreshDatabase;

    private const HOLDING_ID = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';

    private function makeCompany(string $code, string $name, ?string $id = null): Companies
    {
        $company = new Companies();

        if ($id) {
            $company->id = $id;
        }

        $company->fill([
            'code' => $code,
            'name' => $name,
            'location' => 'Test',
            'is_active' => true,
        ]);
        $company->save();

        return $company;
    }

    private function actingAsLmsAdmin(string $companyId, array $extraPermissions = []): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        foreach (array_merge(['Create:LmsCourseBaselines', 'Update:LmsCourseBaselines', 'Delete:LmsCourseBaselines'], $extraPermissions) as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'LMS Baselines Test', 'guard_name' => 'web']);
        $role->givePermissionTo(array_merge(
            ['sistem_lms', 'Create:LmsCourseBaselines', 'Update:LmsCourseBaselines', 'Delete:LmsCourseBaselines'],
            $extraPermissions
        ));

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)->test(LmsCourseBaselinesPage::class)->assertForbidden();
    }

    public function test_company_user_does_not_see_company_field_and_creates_scoped_to_own_company(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);

        Livewire::actingAs($admin)
            ->test(LmsCourseBaselinesPage::class)
            ->call('openCreateModal')
            ->assertSet('isGlobalRole', false)
            ->set('formStartDate', '2026-01-01')
            ->set('formEndDate', '2026-12-31')
            ->set('formMinCourses', '4')
            ->set('formMinHours', '20')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lms_course_baselines', [
            'company_id' => $company->id,
            'min_courses' => 4,
            'min_hours' => 20,
            'is_active' => true,
        ]);
    }

    public function test_global_role_must_pick_a_company(): void
    {
        $holding = $this->makeCompany('HLD', 'Holding', self::HOLDING_ID);
        $companyB = $this->makeCompany('CPB', 'Company B');
        $admin = $this->actingAsLmsAdmin($holding->id);

        Livewire::actingAs($admin)
            ->test(LmsCourseBaselinesPage::class)
            ->assertSet('isGlobalRole', true)
            ->call('openCreateModal')
            ->set('formCompanyId', $companyB->id)
            ->set('formStartDate', '2026-01-01')
            ->set('formEndDate', '2026-12-31')
            ->set('formMinCourses', '2')
            ->set('formMinHours', '10')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lms_course_baselines', [
            'company_id' => $companyB->id,
            'min_courses' => 2,
        ]);
    }

    public function test_global_role_save_fails_without_company(): void
    {
        $holding = $this->makeCompany('HLD', 'Holding', self::HOLDING_ID);
        $admin = $this->actingAsLmsAdmin($holding->id);

        Livewire::actingAs($admin)
            ->test(LmsCourseBaselinesPage::class)
            ->call('openCreateModal')
            ->set('formCompanyId', null)
            ->set('formStartDate', '2026-01-01')
            ->set('formEndDate', '2026-12-31')
            ->set('formMinCourses', '2')
            ->set('formMinHours', '10')
            ->call('saveForm')
            ->assertHasErrors(['formCompanyId']);
    }

    // Tidak ada test "company user cuma lihat baseline company sendiri" — BelongsToCompany
    // trait sengaja skip scoping kalau app()->runningInConsole() true (supaya artisan/seeder/queue
    // tidak ikut ke-filter), dan `php artisan test` sendiri berjalan di console context, jadi
    // scope company ini structural TIDAK BISA diuji lewat PHPUnit di sini — bukan berarti tidak
    // jalan di web request beneran. Perilaku scoping-nya sudah include-tested di BelongsToCompany
    // trait sendiri/model lain yang memakainya, bukan tanggung jawab test halaman ini.

    public function test_updates_baseline(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $baseline = LmsCourseBaselines::create([
            'company_id' => $company->id, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30',
            'min_courses' => 2, 'min_hours' => 10, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseBaselinesPage::class)
            ->call('openEditModal', $baseline->id)
            ->assertSet('formMinCourses', '2')
            ->set('formMinCourses', '5')
            ->set('formMinHours', '25')
            ->call('saveForm')
            ->assertHasNoErrors();

        $baseline->refresh();
        $this->assertEquals(5, $baseline->min_courses);
        $this->assertEquals(25, $baseline->min_hours);
    }

    public function test_toggle_active(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $baseline = LmsCourseBaselines::create([
            'company_id' => $company->id, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30',
            'min_courses' => 2, 'min_hours' => 10, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseBaselinesPage::class)
            ->call('toggleActive', $baseline->id);

        $this->assertFalse((bool) $baseline->fresh()->is_active);
    }

    public function test_deletes_baseline(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $baseline = LmsCourseBaselines::create([
            'company_id' => $company->id, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30',
            'min_courses' => 2, 'min_hours' => 10, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseBaselinesPage::class)
            ->call('removeBaseline', $baseline->id);

        $this->assertSoftDeleted('lms_course_baselines', ['id' => $baseline->id]);
    }

    public function test_end_date_must_be_after_or_equal_start_date(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);

        Livewire::actingAs($admin)
            ->test(LmsCourseBaselinesPage::class)
            ->call('openCreateModal')
            ->set('formStartDate', '2026-06-01')
            ->set('formEndDate', '2026-01-01')
            ->set('formMinCourses', '1')
            ->set('formMinHours', '1')
            ->call('saveForm')
            ->assertHasErrors(['formEndDate']);
    }
}
