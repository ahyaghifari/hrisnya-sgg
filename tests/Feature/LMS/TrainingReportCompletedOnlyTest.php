<?php

namespace Tests\Feature\LMS;

use App\Enum\LsmCourseStatus;
use App\Livewire\LMS\TrainingReportPage;
use App\Models\LMS\LmsCourseBaselines;
use App\Models\LMS\LmsCourseMeetingUsers;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingReportCompletedOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_completed_participants_count_in_report(): void
    {
        $company = new Companies;
        $company->fill(['code' => 'CPX', 'name' => 'Company X', 'location' => 'T', 'is_active' => true]);
        $company->save();

        $user = User::factory()->create(['company_id' => $company->id]);

        $makeCourse = function (string $code, string $status) use ($user) {
            $course = LmsCourses::create(['code' => $code, 'title' => $code, 'status' => LsmCourseStatus::Published->value]);
            $meeting = $course->meetings()->create(['title' => 'P1', 'start_time' => '09:00', 'end_time' => '11:00']);
            LmsCourseUsers::create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => $status, 'progress' => 0, 'score' => 0, 'enrolled_at' => now()]);
            LmsCourseMeetingUsers::create(['course_id' => $course->id, 'meeting_id' => $meeting->id, 'user_id' => $user->id, 'absen_masuk' => true, 'absen_pulang' => true]);

            return $course;
        };

        $done = $makeCourse('DONE', 'completed');
        $makeCourse('OPEN', 'in_progress');

        $baseline = LmsCourseBaselines::create([
            'company_id' => $company->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'min_courses' => 1,
            'min_hours' => 1,
            'is_active' => true,
        ]);

        $page = new TrainingReportPage;
        $page->baselineId = $baseline->id;

        $this->assertSame([$done->id], $page->baselineCourses->pluck('id')->all());

        $row = collect($page->reportMatrix)->firstWhere('user_id', $user->id);
        $this->assertSame(1, $row['total_courses']);
        $this->assertEquals(2.0, $row['total_hours']);
    }
}
