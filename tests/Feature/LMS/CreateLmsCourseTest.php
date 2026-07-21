<?php

namespace Tests\Feature\LMS;

use App\Enum\LsmCourseStatus;
use App\Livewire\LMS\CreateLmsCourse;
use App\Models\LMS\LmsCategories;
use App\Models\Master\Companies;
use App\Models\Master\Competencies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateLmsCourseTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(): User
    {
        $company = new Companies();
        $company->fill([
            'code' => 'CPA',
            'name' => 'Company A',
            'location' => 'Test',
            'is_active' => true,
        ]);
        $company->save();

        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS Create Course Test', 'guard_name' => 'web']);
        $role->givePermissionTo('sistem_lms');

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    private function baseFormData(): array
    {
        $category = LmsCategories::create(['name' => 'IT']);
        $competency = Competencies::create(['name' => 'Komunikasi', 'type' => 'soft-skill']);

        return [
            'category_id' => $category->id,
            'code' => 'TRN-001',
            'title' => 'Pelatihan Uji',
            'type_nakes' => 'all',
            'minimum_score' => 80,
            'competencyIds' => [$competency->id],
        ];
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(CreateLmsCourse::class)->assertForbidden();
    }

    public function test_creates_single_day_course_as_draft(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $data = $this->baseFormData();

        $component = Livewire::actingAs($admin)->test(CreateLmsCourse::class);

        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        $component->set('start_date', '2026-08-10')
            ->call('save');

        $this->assertDatabaseHas('lms_courses', [
            'code' => 'TRN-001',
            'title' => 'Pelatihan Uji',
            'source_type' => 'internal',
            'status' => LsmCourseStatus::Draft->value,
        ]);

        $course = \App\Models\LMS\LmsCourses::where('code', 'TRN-001')->first();
        $this->assertNotNull($course);
        $this->assertEquals('2026-08-10', $course->start_date);
        $this->assertEquals('2026-08-10', $course->end_date);

        $this->assertDatabaseHas('lms_meetings', [
            'course_id' => $course->id,
            'title' => 'Pelatihan Uji',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);

        $component->assertRedirect(route('pelatihan.kelola.show', $course));
    }

    public function test_creates_multi_day_course_as_draft(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $data = $this->baseFormData();

        $component = Livewire::actingAs($admin)->test(CreateLmsCourse::class);

        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('toggleOneDay')
            ->set('start_date', '2026-08-10')
            ->set('end_date', '2026-08-12')
            ->call('save');

        $course = \App\Models\LMS\LmsCourses::where('code', 'TRN-001')->first();
        $this->assertNotNull($course);
        $this->assertEquals(LsmCourseStatus::Draft->value, $course->status);
        $this->assertEquals('2026-08-10', $course->start_date);
        $this->assertEquals('2026-08-12', $course->end_date);
    }

    public function test_requires_at_least_one_competency(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $data = $this->baseFormData();
        unset($data['competencyIds']);

        $component = Livewire::actingAs($admin)->test(CreateLmsCourse::class);

        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        $component->set('start_date', '2026-08-10')
            ->call('save')
            ->assertHasErrors(['competencyIds']);
    }

    public function test_end_date_must_be_after_or_equal_start_date(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $data = $this->baseFormData();

        $component = Livewire::actingAs($admin)->test(CreateLmsCourse::class);

        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        $component->call('toggleOneDay')
            ->set('start_date', '2026-08-10')
            ->set('end_date', '2026-08-01')
            ->call('save')
            ->assertHasErrors(['end_date']);
    }
}
