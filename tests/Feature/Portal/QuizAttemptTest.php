<?php

namespace Tests\Feature\Portal;

use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\LMS\LmsQuestionOptions;
use App\Models\LMS\LmsQuizQuestions;
use App\Models\LMS\LmsQuizResults;
use App\Models\LMS\LmsRespondent;
use App\Models\LMS\LmsQuizzes;
use App\Enum\QuestionType;
use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{meetingQuiz: LmsMeetingQuizzes, correctOptionId: string}
     */
    private function createMultipleChoiceQuiz(LmsMeetings $meeting): array
    {
        $quiz = LmsQuizzes::create(['title' => 'Quiz Dasar', 'total_score' => 0]);

        $question = LmsQuestionBanks::create([
            'question' => '2 + 2 = ?',
            'type' => QuestionType::MultipleChoice,
            'max_score' => 0,
            'min_score' => 0,
        ]);

        $correctOption = LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => '4', 'is_correct' => true]);
        LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => '5', 'is_correct' => false]);

        LmsQuizQuestions::create(['quiz_id' => $quiz->id, 'question_bank_id' => $question->id]);

        $meetingQuiz = LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id, 'duration' => 10, 'is_unlocked' => true]);

        return ['meetingQuiz' => $meetingQuiz, 'correctOptionId' => $correctOption->id];
    }

    private function approveUserForCourse(User $user, LmsCourses $course): void
    {
        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'progress' => 0,
            'score' => 0,
            'enrolled_at' => now(),
        ]);
    }

    public function test_user_not_approved_sees_blocked_message(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q01', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Pertemuan 1']);
        ['meetingQuiz' => $meetingQuiz] = $this->createMultipleChoiceQuiz($meeting);

        $response = $this->actingAs($user)->get('/pelatihan/quiz/' . $meetingQuiz->id);

        $response->assertOk();
        $response->assertSee('Belum bisa mengerjakan kuis ini');
        $response->assertDontSee('2 + 2 = ?');
    }

    public function test_approved_user_sees_questions_without_leaking_correct_answer(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q02', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Pertemuan 1']);
        ['meetingQuiz' => $meetingQuiz] = $this->createMultipleChoiceQuiz($meeting);
        $this->approveUserForCourse($user, $course);

        $response = $this->actingAs($user)->get('/pelatihan/quiz/' . $meetingQuiz->id);

        $response->assertOk();
        $response->assertSee('2 + 2 = ?');
        $response->assertSee('4', false);
        $response->assertDontSee('is_correct');
    }

    public function test_submitting_valid_answers_creates_result_and_hides_form_on_revisit(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q03', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Pertemuan 1']);
        ['meetingQuiz' => $meetingQuiz, 'correctOptionId' => $correctOptionId] = $this->createMultipleChoiceQuiz($meeting);
        $this->approveUserForCourse($user, $course);

        $questionId = $meetingQuiz->quiz->questions->first()->id;

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\QuizAttempt::class, ['meetingQuiz' => $meetingQuiz])
            ->set("answers.$questionId", $correctOptionId)
            ->call('submit');

        $respondentId = LmsRespondent::where('course_id', $course->id)->where('user_id', $user->id)->value('id');

        $this->assertSame(
            1,
            LmsQuizResults::where('meeting_quiz_id', $meetingQuiz->id)->where('respondent_id', $respondentId)->count()
        );

        $response = $this->actingAs($user)->get('/pelatihan/quiz/' . $meetingQuiz->id);

        $response->assertOk();
        $response->assertSee('Kuis sudah kamu kerjakan');
        $response->assertDontSee('Kirim Jawaban');
    }

    public function test_result_page_shows_review_with_wrong_and_correct_option(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q07', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Pertemuan 1']);
        ['meetingQuiz' => $meetingQuiz] = $this->createMultipleChoiceQuiz($meeting);
        $this->approveUserForCourse($user, $course);

        $question = $meetingQuiz->quiz->questions->first();
        $wrongOptionId = $question->options->firstWhere('is_correct', false)->id;

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\QuizAttempt::class, ['meetingQuiz' => $meetingQuiz])
            ->call('selectAnswer', $question->id, $wrongOptionId)
            ->call('submit');

        $response = $this->actingAs($user)->get('/pelatihan/quiz/' . $meetingQuiz->id);

        $response->assertOk();
        $response->assertSee('Pembahasan');
        $response->assertSee('Salah');
        $response->assertSee('Pilihanmu');
        // Jawaban benar ("4") harus tetap tampil di pembahasan meski bukan yang dipilih user.
        $response->assertSee('4', false);
        $response->assertSee('5', false);
    }

    public function test_selected_answer_survives_reload_but_not_new_session(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q06', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Pertemuan 1']);
        ['meetingQuiz' => $meetingQuiz, 'correctOptionId' => $correctOptionId] = $this->createMultipleChoiceQuiz($meeting);
        $this->approveUserForCourse($user, $course);

        $questionId = $meetingQuiz->quiz->questions->first()->id;

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\QuizAttempt::class, ['meetingQuiz' => $meetingQuiz])
            ->call('selectAnswer', $questionId, $correctOptionId);

        // "Reload" — mount() baru, session yang sama (belum logout) — jawaban harus tetap ada.
        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\QuizAttempt::class, ['meetingQuiz' => $meetingQuiz])
            ->assertSet("answers.$questionId", $correctOptionId);

        // Session baru (analog logout lalu login lagi) — jawaban yang belum disubmit boleh hilang.
        $this->flushSession();

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\QuizAttempt::class, ['meetingQuiz' => $meetingQuiz])
            ->assertSet('answers', []);
    }

    public function test_header_shows_trainer_name_and_schedule(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q05', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $trainer = Trainers::create(['name' => 'dr. Budi Santoso']);
        $meeting = LmsMeetings::create([
            'course_id' => $course->id,
            'title' => 'Pertemuan 1',
            'trainer_id' => $trainer->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);
        ['meetingQuiz' => $meetingQuiz] = $this->createMultipleChoiceQuiz($meeting);
        $this->approveUserForCourse($user, $course);

        $response = $this->actingAs($user)->get('/pelatihan/quiz/' . $meetingQuiz->id);

        $response->assertOk();
        $response->assertSee('Quiz Dasar');
        $response->assertSee('dr. Budi Santoso');
        $response->assertSee('01 Agt 2026');
        $response->assertSee('09:00-11:00', false);
    }

    public function test_quiz_with_only_essay_questions_shows_empty_state(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q04', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Pertemuan 1']);
        $this->approveUserForCourse($user, $course);

        $quiz = LmsQuizzes::create(['title' => 'Quiz Esai', 'total_score' => 0]);
        $question = LmsQuestionBanks::create(['question' => 'Jelaskan BHD', 'type' => QuestionType::Essay, 'max_score' => 10, 'min_score' => 0]);
        LmsQuizQuestions::create(['quiz_id' => $quiz->id, 'question_bank_id' => $question->id]);
        $meetingQuiz = LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id, 'is_unlocked' => true]);

        $response = $this->actingAs($user)->get('/pelatihan/quiz/' . $meetingQuiz->id);

        $response->assertOk();
        $response->assertSee('Belum ada soal pilihan ganda di kuis ini.');
        $response->assertDontSee('Kirim Jawaban');
    }

    public function test_locked_quiz_shows_locked_message_even_for_approved_user(): void
    {
        $user = User::factory()->create();
        $course = LmsCourses::create(['code' => 'Q08', 'title' => 'Kursus Quiz', 'status' => LsmCourseStatus::Published->value]);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Pertemuan 1']);
        $quiz = LmsQuizzes::create(['title' => 'Quiz Terkunci', 'total_score' => 0]);
        $meetingQuiz = LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);
        $this->approveUserForCourse($user, $course);

        $response = $this->actingAs($user)->get('/pelatihan/quiz/' . $meetingQuiz->id);

        $response->assertOk();
        $response->assertSee('Kuis belum dibuka');
        $response->assertDontSee('Kirim Jawaban');
    }
}
