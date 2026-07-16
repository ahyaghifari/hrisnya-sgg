<?php

namespace Tests\Feature\Portal;

use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMaterials;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/pelatihan');

        $response->assertRedirect('/login');
    }

    public function test_user_sees_published_course_but_not_draft(): void
    {
        $user = User::factory()->create();

        $published = LmsCourses::create([
            'code' => 'PUB01',
            'title' => 'Pelatihan Terbit',
            'status' => LsmCourseStatus::Published->value,
        ]);

        $draft = LmsCourses::create([
            'code' => 'DFT01',
            'title' => 'Pelatihan Draft',
            'status' => LsmCourseStatus::Draft->value,
        ]);

        $response = $this->actingAs($user)->get('/pelatihan');

        $response->assertOk();
        $response->assertSee('Pelatihan Terbit');
        $response->assertDontSee('Pelatihan Draft');
    }

    public function test_category_filter_returns_only_matching_courses(): void
    {
        $user = User::factory()->create();

        $categoryA = LmsCategories::create(['name' => 'BHD & PPI']);
        $categoryB = LmsCategories::create(['name' => 'K3RS']);

        LmsCourses::create([
            'category_id' => $categoryA->id,
            'code' => 'A01',
            'title' => 'Kursus A',
            'status' => LsmCourseStatus::Published->value,
        ]);

        LmsCourses::create([
            'category_id' => $categoryB->id,
            'code' => 'B01',
            'title' => 'Kursus B',
            'status' => LsmCourseStatus::Published->value,
        ]);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\TrainingList::class)
            ->set('categoryId', $categoryA->id)
            ->assertSee('Kursus A')
            ->assertDontSee('Kursus B');
    }

    public function test_search_filters_courses_by_title_or_code(): void
    {
        $user = User::factory()->create();

        LmsCourses::create([
            'code' => 'BHD2026',
            'title' => 'Bantuan Hidup Dasar',
            'status' => LsmCourseStatus::Published->value,
        ]);

        LmsCourses::create([
            'code' => 'K3RS01',
            'title' => 'Kesehatan Keselamatan Kerja',
            'status' => LsmCourseStatus::Published->value,
        ]);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\TrainingList::class)
            ->set('search', 'Bantuan Hidup')
            ->assertSee('Bantuan Hidup Dasar')
            ->assertDontSee('Kesehatan Keselamatan Kerja');
    }

    public function test_follow_status_filter_separates_followed_and_not_followed(): void
    {
        $user = User::factory()->create();

        $followed = LmsCourses::create([
            'code' => 'FLW1',
            'title' => 'Kursus Diikuti',
            'status' => LsmCourseStatus::Published->value,
        ]);

        LmsCourses::create([
            'code' => 'NFW1',
            'title' => 'Kursus Belum Diikuti',
            'status' => LsmCourseStatus::Published->value,
        ]);

        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $followed->id,
            'status' => 'requested',
            'progress' => 0,
            'score' => 0,
            'enrolled_at' => now(),
        ]);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\TrainingList::class)
            ->set('followStatus', 'followed')
            ->assertSee('Kursus Diikuti')
            ->assertDontSee('Kursus Belum Diikuti')
            ->set('followStatus', 'not_followed')
            ->assertDontSee('Kursus Diikuti')
            ->assertSee('Kursus Belum Diikuti');
    }

    public function test_follow_course_from_detail_page_creates_course_user_once(): void
    {
        $user = User::factory()->create();

        $course = LmsCourses::create([
            'code' => 'F01',
            'title' => 'Kursus Follow',
            'status' => LsmCourseStatus::Published->value,
        ]);

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\TrainingDetail::class, ['course' => $course]);

        $component->call('followCourse');
        $component->call('followCourse');

        $this->assertSame(1, LmsCourseUsers::where('user_id', $user->id)->where('course_id', $course->id)->count());
        $component->assertSee('Menunggu persetujuan');
    }

    public function test_approved_course_user_sees_diikuti_instead_of_pending(): void
    {
        $user = User::factory()->create();

        $course = LmsCourses::create([
            'code' => 'A01',
            'title' => 'Kursus Approved',
            'status' => LsmCourseStatus::Published->value,
        ]);

        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'progress' => 0,
            'score' => 0,
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/pelatihan/' . $course->id);

        $response->assertOk();
        $response->assertSee('sudah mengikuti pelatihan ini');
        $response->assertDontSee('Menunggu persetujuan');
    }

    public function test_member_search_filters_participant_list_on_detail_page(): void
    {
        $viewer = User::factory()->create();
        $budi = User::factory()->create(['name' => 'Budi Santoso']);
        $siti = User::factory()->create(['name' => 'Siti Aminah']);

        $course = LmsCourses::create([
            'code' => 'M01',
            'title' => 'Kursus Member',
            'status' => LsmCourseStatus::Published->value,
        ]);

        foreach ([$budi, $siti] as $participant) {
            LmsCourseUsers::create([
                'user_id' => $participant->id,
                'course_id' => $course->id,
                'status' => 'approved',
                'progress' => 0,
                'score' => 0,
                'enrolled_at' => now(),
            ]);
        }

        \Livewire\Livewire::actingAs($viewer)
            ->test(\App\Livewire\Pelatihan\TrainingDetail::class, ['course' => $course])
            ->set('memberSearch', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }

    public function test_detail_page_shows_trainer_name(): void
    {
        $user = User::factory()->create();

        $course = LmsCourses::create([
            'code' => 'T01',
            'title' => 'Kursus Trainer',
            'status' => LsmCourseStatus::Published->value,
        ]);

        $trainer = Trainers::create(['name' => 'dr. Andi Wijaya']);

        LmsMeetings::create([
            'course_id' => $course->id,
            'title' => 'Sesi Trainer',
            'trainer_id' => $trainer->id,
        ]);

        $response = $this->actingAs($user)->get('/pelatihan/' . $course->id);

        $response->assertOk();
        $response->assertSee('dr. Andi Wijaya');
    }

    public function test_detail_page_shows_meeting_and_material_link(): void
    {
        $user = User::factory()->create();

        $course = LmsCourses::create([
            'code' => 'D01',
            'title' => 'Kursus Detail',
            'status' => LsmCourseStatus::Published->value,
        ]);

        $meeting = LmsMeetings::create([
            'course_id' => $course->id,
            'title' => 'Pertemuan 1',
        ]);

        LmsMaterials::create([
            'meeting_id' => $meeting->id,
            'title' => 'Materi PDF',
            'file_path' => 'lms/materials/dummy.pdf',
        ]);

        $response = $this->actingAs($user)->get('/pelatihan/' . $course->id);

        $response->assertOk();
        $response->assertSee('Pertemuan 1');
        $response->assertSee('Materi PDF');
        $response->assertSee('storage/lms/materials/dummy.pdf', false);
    }

    public function test_detail_page_shows_all_meetings_as_accordion_when_more_than_one(): void
    {
        $user = User::factory()->create();

        $course = LmsCourses::create([
            'code' => 'D02',
            'title' => 'Kursus Multi Pertemuan',
            'status' => LsmCourseStatus::Published->value,
        ]);

        LmsMeetings::create(['course_id' => $course->id, 'title' => 'Sesi Pertama']);
        LmsMeetings::create(['course_id' => $course->id, 'title' => 'Sesi Kedua']);

        $response = $this->actingAs($user)->get('/pelatihan/' . $course->id);

        $response->assertOk();
        $response->assertSee('Sesi Pertama');
        $response->assertSee('Sesi Kedua');
    }
}
