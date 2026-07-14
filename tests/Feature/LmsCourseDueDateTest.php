<?php

namespace Tests\Feature;

use App\Enum\CourseUserStatus;
use App\Http\Resources\LMS\CourseDetailsResource;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LmsCourseDueDateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Companies
    {
        return Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);
    }

    public function test_course_is_overdue_only_when_due_date_passed_and_not_completed(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $overdueCourse = LmsCourses::create([
            'code' => 'CRS-OVERDUE',
            'title' => 'Course Lewat Deadline',
            'type_nakes' => 'all',
            'source_type' => 'internal',
            'type' => 'seasonal',
            'due_date' => now()->subDay(),
        ]);
        $completedOverdueCourse = LmsCourses::create([
            'code' => 'CRS-DONE',
            'title' => 'Course Selesai Walau Lewat',
            'type_nakes' => 'all',
            'source_type' => 'internal',
            'type' => 'seasonal',
            'due_date' => now()->subDay(),
        ]);
        $futureCourse = LmsCourses::create([
            'code' => 'CRS-FUTURE',
            'title' => 'Course Belum Deadline',
            'type_nakes' => 'all',
            'source_type' => 'internal',
            'type' => 'seasonal',
            'due_date' => now()->addWeek(),
        ]);

        $this->assertTrue($overdueCourse->isOverdue());
        $this->assertTrue($futureCourse->isOverdue() === false);

        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $completedOverdueCourse->id,
            'status' => CourseUserStatus::COMPLETED->value,
            'enrolled_at' => now(),
        ]);
        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $overdueCourse->id,
            'status' => CourseUserStatus::APPROVED->value,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum');

        $completedOverdueCourse->load('courseUsers.user.profile', 'category', 'meetings', 'userHistories', 'feedbacks');
        $overdueCourse->load('courseUsers.user.profile', 'category', 'meetings', 'userHistories', 'feedbacks');

        $completedArray = (new CourseDetailsResource($completedOverdueCourse))->toArray(Request::create('/'));
        $this->assertFalse($completedArray['is_overdue']);

        $inProgressArray = (new CourseDetailsResource($overdueCourse))->toArray(Request::create('/'));
        $this->assertTrue($inProgressArray['is_overdue']);
    }
}
