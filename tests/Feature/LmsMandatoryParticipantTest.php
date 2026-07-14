<?php

namespace Tests\Feature;

use App\Http\Resources\LMS\CourseDetailsResource;
use App\Http\Resources\LMS\CoursesResource;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\Master\Companies;
use App\Models\User;
use App\Repositories\LMS\CourseUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LmsMandatoryParticipantTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(): LmsCourses
    {
        return LmsCourses::create([
            'code' => 'CRS-' . uniqid(),
            'title' => 'Course Test',
            'type_nakes' => 'all',
            'source_type' => 'internal',
            'type' => 'seasonal',
            'minimum_score' => 70,
        ]);
    }

    public function test_self_enrolled_participant_is_not_mandatory_but_admin_assigned_is(): void
    {
        $company = Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);
        $selfEnrolled = User::factory()->create(['company_id' => $company->id]);
        $adminAssigned = User::factory()->create(['company_id' => $company->id]);
        $course = $this->makeCourse();

        app(CourseUserRepository::class)->assignUserToCourse($selfEnrolled->id, $course->id);
        LmsCourseUsers::create([
            'user_id' => $adminAssigned->id,
            'course_id' => $course->id,
            'is_mandatory' => true,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        $this->assertFalse((bool) LmsCourseUsers::where('user_id', $selfEnrolled->id)->first()->is_mandatory);
        $this->assertTrue((bool) LmsCourseUsers::where('user_id', $adminAssigned->id)->first()->is_mandatory);
    }

    public function test_is_mandatory_exposed_in_course_api_resources(): void
    {
        $company = Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $course = $this->makeCourse();

        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'is_mandatory' => true,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum');
        $course->load('courseUsers.user.profile', 'category', 'meetings', 'userHistories', 'feedbacks');

        $listArray = (new CoursesResource($course))->toArray(Request::create('/'));
        $this->assertTrue($listArray['is_mandatory']);

        $detailArray = (new CourseDetailsResource($course))->toArray(Request::create('/'));
        $this->assertTrue($detailArray['is_mandatory']);
    }
}
