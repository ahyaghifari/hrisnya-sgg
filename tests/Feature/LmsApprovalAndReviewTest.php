<?php

namespace Tests\Feature;

use App\Enum\CourseUserStatus;
use App\Enum\QuestionType;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\LmsQuizQuestions;
use App\Models\LMS\LmsQuizResults;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\LMS\LmsQuestionOptions;
use App\Models\Master\Companies;
use App\Models\User;
use App\Repositories\LMS\CourseUserRepository;
use App\Services\LMS\CourseUserService;
use App\Services\LMS\MeetingAttendanceService;
use App\Services\LMS\QuizService;
use App\Services\LMS\RespondentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LmsApprovalAndReviewTest extends TestCase
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

    private function makeCourseWithMeeting(Companies $company): array
    {
        $course = LmsCourses::create([
            'code' => 'CRS-' . uniqid(),
            'title' => 'Course Test',
            'type_nakes' => 'all',
            'source_type' => 'internal',
            'type' => 'seasonal',
            'minimum_score' => 70,
        ]);

        $meeting = LmsMeetings::create([
            'course_id' => $course->id,
            'title' => 'Meeting 1',
            'start_date' => now()->subHour()->toDateString(),
            'end_date' => now()->addHour()->toDateString(),
            'start_time' => now()->subHour()->toTimeString(),
            'end_time' => now()->addHour()->toTimeString(),
            'attendance_type' => 'online',
        ]);

        return compact('course', 'meeting');
    }

    private function makeQuizWithEssay(LmsMeetings $meeting): array
    {
        $quiz = LmsQuizzes::create(['title' => 'Quiz Test', 'total_score' => 100]);

        $essayQuestion = LmsQuestionBanks::create([
            'question' => 'Jelaskan sesuatu',
            'type' => QuestionType::Essay,
            'max_score' => 20,
        ]);

        LmsQuizQuestions::create(['quiz_id' => $quiz->id, 'question_bank_id' => $essayQuestion->id]);

        $meetingQuiz = LmsMeetingQuizzes::create([
            'meeting_id' => $meeting->id,
            'quiz_id' => $quiz->id,
            'duration' => 30,
        ]);

        return compact('quiz', 'essayQuestion', 'meetingQuiz');
    }

    public function test_participant_blocked_from_checkin_and_quiz_until_approved(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        ['course' => $course, 'meeting' => $meeting] = $this->makeCourseWithMeeting($company);
        ['meetingQuiz' => $meetingQuiz] = $this->makeQuizWithEssay($meeting);

        app(CourseUserRepository::class)->assignUserToCourse($user->id, $course->id);
        $courseUser = LmsCourseUsers::where('user_id', $user->id)->where('course_id', $course->id)->first();
        $this->assertSame(CourseUserStatus::REQUESTED->value, $courseUser->status);

        $this->actingAs($user);

        $checkin = app(MeetingAttendanceService::class)->clockIn($meeting->id);
        $this->assertFalse($checkin['success']);
        $this->assertDatabaseMissing('lms_meeting_attendances', ['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        $respondent = app(RespondentService::class)->resolveForEmployee($user->id, $course->id);
        $submit = app(QuizService::class)->submitQuiz($meetingQuiz->id, [], $respondent->id, $user->id);
        $this->assertFalse($submit['success']);
        $this->assertDatabaseMissing('lms_quiz_results', ['meeting_quiz_id' => $meetingQuiz->id, 'respondent_id' => $respondent->id]);

        $courseUser->update(['status' => CourseUserStatus::APPROVED->value]);

        $checkinAfterApproval = app(MeetingAttendanceService::class)->clockIn($meeting->id);
        $this->assertTrue($checkinAfterApproval['success']);
        $this->assertDatabaseHas('lms_meeting_attendances', ['meeting_id' => $meeting->id, 'user_id' => $user->id]);
    }

    public function test_essay_answer_pending_review_then_scored_by_admin(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        ['course' => $course, 'meeting' => $meeting] = $this->makeCourseWithMeeting($company);
        ['essayQuestion' => $essayQuestion, 'meetingQuiz' => $meetingQuiz] = $this->makeQuizWithEssay($meeting);

        app(CourseUserRepository::class)->assignUserToCourse($user->id, $course->id);
        LmsCourseUsers::where('user_id', $user->id)->update(['status' => CourseUserStatus::APPROVED->value]);

        $respondent = app(RespondentService::class)->resolveForEmployee($user->id, $course->id);
        $result = app(QuizService::class)->submitQuiz($meetingQuiz->id, [
            ['question_id' => $essayQuestion->id, 'answer_text' => 'Jawaban esai peserta'],
        ], $respondent->id, $user->id);

        $this->assertTrue($result['has_essay']);
        $this->assertSame('pending_review', $result['status']);

        $storedResult = LmsQuizResults::where('meeting_quiz_id', $meetingQuiz->id)->where('respondent_id', $respondent->id)->first();
        $this->assertSame('pending_review', $storedResult->status);
        $this->assertSame(0, $storedResult->total_score);

        $essayAnswer = \App\Models\LMS\LmsQuizAnswers::where('meeting_quiz_id', $meetingQuiz->id)
            ->where('respondent_id', $respondent->id)
            ->where('question_id', $essayQuestion->id)
            ->firstOrFail();

        app(QuizService::class)->reviewEssayAnswers(
            $meetingQuiz->id,
            $respondent->id,
            [$essayAnswer->id => 15],
            $admin->id,
        );

        $storedResult->refresh();
        $this->assertSame('completed', $storedResult->status);
        $this->assertSame(15, $storedResult->total_score);
        $this->assertSame($admin->id, $storedResult->reviewed_by);
        $this->assertNotNull($storedResult->reviewed_at);
    }

    public function test_multiple_choice_score_is_100_divided_by_question_count(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        ['course' => $course, 'meeting' => $meeting] = $this->makeCourseWithMeeting($company);

        $quiz = LmsQuizzes::create(['title' => 'Quiz PG', 'total_score' => 0]);
        $answers = [];
        foreach (range(1, 3) as $i) {
            $question = LmsQuestionBanks::create(['question' => "Soal {$i}", 'type' => QuestionType::MultipleChoice]);
            LmsQuizQuestions::create(['quiz_id' => $quiz->id, 'question_bank_id' => $question->id]);
            $right = LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => 'Benar', 'is_correct' => true]);
            $wrong = LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => 'Salah', 'is_correct' => false]);
            // Soal 1 & 2 dijawab benar, soal 3 salah.
            $answers[] = ['question_id' => $question->id, 'answer_id' => $i < 3 ? $right->id : $wrong->id];
        }
        $meetingQuiz = LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id, 'duration' => 30]);

        app(CourseUserRepository::class)->assignUserToCourse($user->id, $course->id);
        LmsCourseUsers::where('user_id', $user->id)->update(['status' => CourseUserStatus::APPROVED->value]);
        $respondent = app(RespondentService::class)->resolveForEmployee($user->id, $course->id);

        $result = app(QuizService::class)->submitQuiz($meetingQuiz->id, $answers, $respondent->id, $user->id);

        $this->assertSame(67, $result['user_score']); // 2 x 33,33 dibulatkan
        $this->assertSame(100, $result['max_score']);
        $this->assertSame(100, $quiz->fresh()->calculateTotalScore());
    }

    public function test_essay_review_score_is_clamped_to_question_max_score(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        ['course' => $course, 'meeting' => $meeting] = $this->makeCourseWithMeeting($company);
        ['essayQuestion' => $essayQuestion, 'meetingQuiz' => $meetingQuiz] = $this->makeQuizWithEssay($meeting);

        app(CourseUserRepository::class)->assignUserToCourse($user->id, $course->id);
        LmsCourseUsers::where('user_id', $user->id)->update(['status' => CourseUserStatus::APPROVED->value]);

        $respondent = app(RespondentService::class)->resolveForEmployee($user->id, $course->id);
        app(QuizService::class)->submitQuiz($meetingQuiz->id, [
            ['question_id' => $essayQuestion->id, 'answer_text' => 'Jawaban'],
        ], $respondent->id, $user->id);

        $essayAnswer = \App\Models\LMS\LmsQuizAnswers::where('meeting_quiz_id', $meetingQuiz->id)
            ->where('respondent_id', $respondent->id)
            ->firstOrFail();

        // max_score soal ini 20, dicoba kasih 999 — harus diclamp ke 20.
        app(QuizService::class)->reviewEssayAnswers($meetingQuiz->id, $respondent->id, [$essayAnswer->id => 999], $admin->id);

        $storedResult = LmsQuizResults::where('meeting_quiz_id', $meetingQuiz->id)->where('respondent_id', $respondent->id)->first();
        $this->assertSame(20, $storedResult->total_score);
    }

    public function test_certificate_download_blocked_when_not_passed(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);
        ['course' => $course] = $this->makeCourseWithMeeting($company);

        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => CourseUserStatus::APPROVED->value,
            'certificate_url' => 'certificates/x/y.pdf',
            'is_passed' => false,
        ]);

        $url = app(CourseUserService::class)->getDownloadableCertificateUrl($user->id, $course->id);
        $this->assertNull($url);

        LmsCourseUsers::where('user_id', $user->id)->where('course_id', $course->id)->update(['is_passed' => true]);

        $url = app(CourseUserService::class)->getDownloadableCertificateUrl($user->id, $course->id);
        $this->assertSame('certificates/x/y.pdf', $url);
    }
}
