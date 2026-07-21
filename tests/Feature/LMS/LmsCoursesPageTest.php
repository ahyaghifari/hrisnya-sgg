<?php

namespace Tests\Feature\LMS;

use App\Livewire\LMS\LmsCoursesPage;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsCoursesPageTest extends TestCase
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

    private function actingAsLmsAdmin(?string $companyId = null): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS Courses Test', 'guard_name' => 'web']);
        $role->givePermissionTo('sistem_lms');

        $user = User::factory()->create($companyId ? ['company_id' => $companyId] : []);
        $user->assignRole($role);

        return $user;
    }

    private function makeCourse(string $companyId, string $code, string $title, array $overrides = []): LmsCourses
    {
        return LmsCourses::create(array_merge([
            'company_id' => $companyId,
            'code' => $code,
            'title' => $title,
            'source_type' => 'internal',
            'status' => 'published',
        ], $overrides));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(LmsCoursesPage::class)->assertForbidden();
    }

    public function test_scoped_user_only_sees_own_company_courses(): void
    {
        $companyA = $this->makeCompany('CPA', 'Company A');
        $companyB = $this->makeCompany('CPB', 'Company B');
        $admin = $this->actingAsLmsAdmin($companyA->id);

        $this->makeCourse($companyA->id, 'TRN-A', 'Pelatihan A');
        $this->makeCourse($companyB->id, 'TRN-B', 'Pelatihan B');

        Livewire::actingAs($admin)
            ->test(LmsCoursesPage::class)
            ->assertSee('Pelatihan A')
            ->assertDontSee('Pelatihan B');
    }

    public function test_global_role_can_browse_all_companies_and_filter(): void
    {
        $holding = $this->makeCompany('HLD', 'Holding', self::HOLDING_ID);
        $companyB = $this->makeCompany('CPB', 'Company B');
        $admin = $this->actingAsLmsAdmin($holding->id);

        $this->makeCourse($holding->id, 'TRN-A', 'Pelatihan A');
        $this->makeCourse($companyB->id, 'TRN-B', 'Pelatihan B');

        Livewire::actingAs($admin)
            ->test(LmsCoursesPage::class)
            ->assertSee('Pelatihan A')
            ->assertSee('Pelatihan B')
            ->set('companyId', $companyB->id)
            ->assertDontSee('Pelatihan A')
            ->assertSee('Pelatihan B');
    }

    public function test_search_filters_by_code_or_title(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);

        $this->makeCourse($company->id, 'TRN-A', 'Pelatihan Dasar');
        $this->makeCourse($company->id, 'TRN-B', 'Pelatihan Lanjutan');

        Livewire::actingAs($admin)
            ->test(LmsCoursesPage::class)
            ->set('search', 'Dasar')
            ->assertSee('Pelatihan Dasar')
            ->assertDontSee('Pelatihan Lanjutan');
    }

    public function test_category_filter(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $categoryIt = LmsCategories::create(['name' => 'IT']);
        $categoryHr = LmsCategories::create(['name' => 'Kepegawaian']);

        $this->makeCourse($company->id, 'TRN-A', 'Pelatihan IT', ['category_id' => $categoryIt->id]);
        $this->makeCourse($company->id, 'TRN-B', 'Pelatihan HR', ['category_id' => $categoryHr->id]);

        Livewire::actingAs($admin)
            ->test(LmsCoursesPage::class)
            ->set('categoryId', $categoryIt->id)
            ->assertSee('Pelatihan IT')
            ->assertDontSee('Pelatihan HR');
    }

    public function test_only_internal_courses_are_shown(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);

        $this->makeCourse($company->id, 'TRN-A', 'Pelatihan Internal', ['source_type' => 'internal']);
        $this->makeCourse($company->id, 'TRN-B', 'Pelatihan Eksternal', ['source_type' => 'external']);

        Livewire::actingAs($admin)
            ->test(LmsCoursesPage::class)
            ->assertSee('Pelatihan Internal')
            ->assertDontSee('Pelatihan Eksternal');
    }
}
