<?php

namespace Tests\Feature\LMS;

use App\Enum\LsmCourseStatus;
use App\Livewire\LMS\LmsCourseDetailPage;
use App\Models\LMS\LmsCourseMeetingUsers;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizResults;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\LmsRespondent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_and_quiz_percentages_per_participant(): void
    {
        $course = LmsCourses::create(['code' => 'PS-1', 'title' => 'K', 'status' => LsmCourseStatus::Published->value]);
        $m1 = $course->meetings()->create(['title' => 'P1']);
        $m2 = $course->meetings()->create(['title' => 'P2']);
        $quiz = LmsQuizzes::create(['title' => 'Q', 'total_score' => 0]);
        $mq1 = LmsMeetingQuizzes::create(['meeting_id' => $m1->id, 'quiz_id' => $quiz->id, 'duration' => 5, 'is_unlocked' => true]);
        $quiz2 = LmsQuizzes::create(['title' => 'Q2', 'total_score' => 0]);
        LmsMeetingQuizzes::create(['meeting_id' => $m2->id, 'quiz_id' => $quiz2->id, 'duration' => 5, 'is_unlocked' => true]);

        $user = User::factory()->create();
        LmsCourseUsers::create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'approved', 'progress' => 0, 'score' => 0, 'enrolled_at' => now()]);

        // Pertemuan 1 hadir penuh, pertemuan 2 hanya absen masuk -> 50%.
        LmsCourseMeetingUsers::create(['course_id' => $course->id, 'meeting_id' => $m1->id, 'user_id' => $user->id, 'absen_masuk' => true, 'absen_pulang' => true]);
        LmsCourseMeetingUsers::create(['course_id' => $course->id, 'meeting_id' => $m2->id, 'user_id' => $user->id, 'absen_masuk' => true, 'absen_pulang' => false]);

        // 1 dari 2 kuis dikerjakan -> 50%.
        $respondent = LmsRespondent::create(['course_id' => $course->id, 'user_id' => $user->id, 'category' => LmsRespondent::CATEGORY_KUIS, 'name' => $user->name]);
        LmsQuizResults::create(['meeting_quiz_id' => $mq1->id, 'quiz_id' => $quiz->id, 'respondent_id' => $respondent->id, 'total_score' => 80, 'max_score' => 100, 'is_completed' => true, 'status' => 'completed']);

        $component = new LmsCourseDetailPage;
        $component->course = $course;

        $stat = $component->participantStats[$user->id];

        $this->assertSame(50, $stat['attendance']);
        $this->assertSame(50, $stat['quiz']);
        $this->assertSame(1, $stat['quizDone']);
        $this->assertSame(2, $stat['quizTotal']);
    }
}
