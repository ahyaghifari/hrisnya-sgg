<?php

namespace Tests\Feature;

use App\Enum\QuestionType;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMaterials;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\LmsQuizQuestions;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\LMS\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsCourseDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_course_copies_structure_but_not_participants(): void
    {
        $company = Companies::create([
            'code' => 'TST',
            'name' => 'Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $participant = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($admin);

        $course = LmsCourses::create([
            'code' => 'ORIG-001',
            'title' => 'Course Asli',
            'type_nakes' => 'all',
            'source_type' => 'internal',
            'type' => 'seasonal',
            'minimum_score' => 70,
        ]);

        $meeting = LmsMeetings::create([
            'course_id' => $course->id,
            'title' => 'Meeting 1',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addHour()->toDateString(),
            'attendance_type' => 'online',
        ]);

        LmsMaterials::create([
            'meeting_id' => $meeting->id,
            'title' => 'Materi 1',
            'content' => 'Isi materi',
        ]);

        $quiz = LmsQuizzes::create(['title' => 'Quiz 1', 'total_score' => 100]);
        $question = LmsQuestionBanks::create([
            'question' => 'Soal 1',
            'type' => QuestionType::MultipleChoice,
            'max_score' => 0,
        ]);
        LmsQuizQuestions::create(['quiz_id' => $quiz->id, 'question_bank_id' => $question->id]);
        LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id, 'duration' => 20]);

        LmsCourseUsers::create([
            'user_id' => $participant->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        $duplicate = app(CourseService::class)->duplicateCourse($course->fresh());

        $this->assertNotSame($course->id, $duplicate->id);
        $this->assertNotSame($course->code, $duplicate->code);
        $this->assertSame('Course Asli (Salinan)', $duplicate->title);

        $this->assertCount(1, $duplicate->meetings);
        $newMeeting = $duplicate->meetings->first();
        $this->assertNotSame($meeting->id, $newMeeting->id);
        $this->assertSame('Meeting 1', $newMeeting->title);

        $this->assertCount(1, $newMeeting->materials);
        $this->assertSame('Materi 1', $newMeeting->materials->first()->title);

        $this->assertCount(1, $newMeeting->quizzes);
        // Quiz bank-nya dipakai bersama (tidak digandakan), sama seperti desain aslinya.
        $this->assertSame($quiz->id, $newMeeting->quizzes->first()->quiz_id);

        // Peserta & riwayat TIDAK ikut disalin — course baru mulai kosong dari sisi peserta.
        $this->assertCount(0, $duplicate->courseUsers);
        $this->assertDatabaseCount('lms_course_users', 1);
    }
}
