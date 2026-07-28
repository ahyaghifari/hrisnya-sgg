<?php

namespace Tests\Feature\LMS;

use App\Livewire\LMS\TrainerDetailPage;
use App\Models\LMS\LmsCourseFeedbacks;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\LmsRespondent;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainerDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Trainers Detail Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $trainer = Trainers::create(['name' => 'Dr. Budi', 'type' => 'external']);

        Livewire::actingAs(User::factory()->create())
            ->test(TrainerDetailPage::class, ['trainer' => $trainer])
            ->assertForbidden();
    }

    public function test_shows_distinct_courses_and_feedback_from_meetings(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $trainer = Trainers::create(['name' => 'Dr. Budi', 'type' => 'external']);
        $participant = User::factory()->create(['name' => 'Peserta A']);

        $course = LmsCourses::create(['code' => 'TRN-010', 'title' => 'Pelatihan Kardiologi']);
        // 2 pertemuan di course yang SAMA — jangan sampai course-nya dobel di tab Pelatihan.
        LmsMeetings::create(['course_id' => $course->id, 'trainer_id' => $trainer->id, 'title' => 'Sesi 1']);
        LmsMeetings::create(['course_id' => $course->id, 'trainer_id' => $trainer->id, 'title' => 'Sesi 2']);

        $respondent = LmsRespondent::create([
            'course_id' => $course->id,
            'user_id' => $participant->id,
            'name' => $participant->name,
            'category' => LmsRespondent::CATEGORY_UMPAN_BALIK,
        ]);

        LmsCourseFeedbacks::create([
            'course_id' => $course->id,
            'respondent_id' => $respondent->id,
            'rating' => 5,
            'feedback' => 'Sangat jelas penjelasannya.',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(TrainerDetailPage::class, ['trainer' => $trainer])
            ->set('tab', 'pelatihan')
            ->assertSee('Pelatihan Kardiologi')
            ->set('tab', 'review')
            ->assertSee('Sangat jelas penjelasannya.')
            ->assertSee('Peserta A');

        $this->assertCount(1, $component->instance()->courses);
        $this->assertCount(1, $component->instance()->reviews);
    }
}
