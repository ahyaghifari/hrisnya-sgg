<?php

namespace Tests\Feature\LMS;

use App\Enum\LsmCourseStatus;
use App\Enum\QuestionType;
use App\Livewire\LMS\PublicFeedbackPage;
use App\Livewire\LMS\PublicQuizPage;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsFeedbackQuestion;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\LMS\LmsQuestionOptions;
use App\Models\LMS\LmsQuizQuestions;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\LmsRespondent;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicQuizAndFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Companies
    {
        return Companies::create([
            'code' => 'PUB',
            'name' => 'Public Test Co',
            'location' => 'Test',
            'is_active' => true,
        ]);
    }

    private function makePublishedCourseWithQuiz(): array
    {
        $course = LmsCourses::create([
            'code' => 'PUB-01',
            'title' => 'Kursus Publik',
            'status' => LsmCourseStatus::Published->value,
            'requires_feedback' => true,
            'feedback_public_token' => 'feedback-token-abc',
        ]);

        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Sesi 1']);

        $quiz = LmsQuizzes::create(['title' => 'Quiz Publik', 'total_score' => 0]);
        $question = LmsQuestionBanks::create([
            'question' => 'Ibukota Indonesia?',
            'type' => QuestionType::MultipleChoice,
            'max_score' => 0,
        ]);
        $correctOption = LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => 'Jakarta', 'is_correct' => true]);
        LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => 'Bandung', 'is_correct' => false]);
        LmsQuizQuestions::create(['quiz_id' => $quiz->id, 'question_bank_id' => $question->id]);

        $meetingQuiz = LmsMeetingQuizzes::create([
            'meeting_id' => $meeting->id,
            'quiz_id' => $quiz->id,
            'duration' => 10,
            'public_token' => 'quiz-token-abc',
        ]);

        return compact('course', 'meeting', 'meetingQuiz', 'correctOption');
    }

    public function test_quiz_page_not_found_for_invalid_token(): void
    {
        Livewire::test(PublicQuizPage::class, ['token' => 'nonexistent'])
            ->assertSet('notFound', true);
    }

    public function test_quiz_page_not_found_when_course_not_published(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'course' => $course] = $this->makePublishedCourseWithQuiz();
        $course->update(['status' => LsmCourseStatus::Draft->value]);

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->assertSet('notFound', true);
    }

    public function test_quiz_closed_when_meeting_date_has_passed(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'meeting' => $meeting] = $this->makePublishedCourseWithQuiz();
        $meeting->update(['start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->subDay()->toDateString()]);

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->assertSet('closed', true)
            ->assertSet('notFound', false);
    }

    public function test_quiz_open_on_meeting_date_itself(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'meeting' => $meeting] = $this->makePublishedCourseWithQuiz();
        $meeting->update(['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()]);

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->assertSet('closed', false);
    }

    public function test_quiz_submit_blocked_server_side_when_closed_after_starting(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'meeting' => $meeting, 'correctOption' => $correctOption] = $this->makePublishedCourseWithQuiz();
        $meeting->update(['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()]);
        $company = $this->makeCompany();
        $question = $meetingQuiz->quiz->questions->first();

        $component = Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Telat')
            ->set('nip', 'GUEST-LATE')
            ->set('company_id', $company->id)
            ->set('unit', 'IGD')
            ->call('startQuiz')
            ->call('selectAnswer', $question->id, $correctOption->id);

        // Tanggal pertemuan sudah lewat SETELAH form kebuka (mis. dibuka H-1, disubmit H+1).
        $meeting->update(['start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->subDay()->toDateString()]);

        $component->call('submit')->assertForbidden();
    }

    public function test_quiz_progress_survives_reload_but_not_new_session(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'correctOption' => $correctOption] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();
        $question = $meetingQuiz->quiz->questions->first();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Reload')
            ->set('nip', 'GUEST-00099')
            ->set('company_id', $company->id)
            ->set('unit', 'IGD')
            ->call('startQuiz')
            ->call('selectAnswer', $question->id, $correctOption->id);

        // "Reload" — mount() baru, session yang sama — identitas & jawaban harus tetap ada.
        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->assertSet('step', 'quiz')
            ->assertSet('name', 'Tamu Reload')
            ->assertSet("answers.{$question->id}", $correctOption->id);

        // Session baru (analog tab/browser lain) — progres yang belum submit boleh hilang.
        $this->flushSession();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->assertSet('step', 'identity')
            ->assertSet('answers', []);
    }

    public function test_quiz_session_cleared_after_submit(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'correctOption' => $correctOption] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();
        $question = $meetingQuiz->quiz->questions->first();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Selesai')
            ->set('nip', 'GUEST-00098')
            ->set('company_id', $company->id)
            ->set('unit', 'IGD')
            ->call('startQuiz')
            ->call('selectAnswer', $question->id, $correctOption->id)
            ->call('submit');

        // Reload setelah submit -> mulai dari awal lagi (sesi attempt itu sudah dibersihkan),
        // bukan malah balik ke tengah kuis yang sudah selesai.
        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->assertSet('step', 'identity');
    }

    public function test_question_and_option_text_strip_html_and_avoid_double_lettering(): void
    {
        ['meetingQuiz' => $meetingQuiz] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();

        // Soal diketik via Trix (rich text) -> tersimpan sebagai HTML. Opsi diketik manual
        // dengan huruf sudah menempel di teksnya sendiri (kebiasaan lama sebagian admin).
        $question = LmsQuestionBanks::create([
            'question' => '<p>Apa ibukota <strong>Indonesia</strong>?</p>',
            'type' => QuestionType::MultipleChoice,
            'max_score' => 0,
        ]);
        LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => 'A. Jakarta', 'is_correct' => true]);
        LmsQuestionOptions::create(['question_id' => $question->id, 'option_text' => 'B. Bandung', 'is_correct' => false]);
        LmsQuizQuestions::create(['quiz_id' => $meetingQuiz->quiz_id, 'question_bank_id' => $question->id]);

        $component = Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Cek Format')
            ->set('nip', 'GUEST-00097')
            ->set('company_id', $company->id)
            ->set('unit', 'IGD')
            ->call('startQuiz');

        $rendered = $component->instance()->questions;
        $newQuestion = collect($rendered)->firstWhere('id', $question->id);

        $this->assertSame('Apa ibukota Indonesia?', $newQuestion['question_text']);

        // Isi opsi (setelah 1 label huruf yang ditambahkan kode dibuang) harus bersih "Jakarta"/
        // "Bandung" saja — tidak boleh ada sisa label huruf lain nempel dari data lama.
        $cleanedContents = collect($newQuestion['options'])
            ->map(fn ($o) => preg_replace('/^[A-Z]\.\s/', '', $o['text'], 1))
            ->all();

        $this->assertEqualsCanonicalizing(['Jakarta', 'Bandung'], $cleanedContents);
    }

    public function test_public_can_submit_quiz_and_creates_separate_respondent(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'correctOption' => $correctOption, 'course' => $course] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();

        $question = $meetingQuiz->quiz->questions->first();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Eksternal')
            ->set('nip', 'GUEST-00001')
            ->set('company_id', $company->id)
            ->set('unit', 'Perawat - IGD')
            ->call('startQuiz')
            ->assertSet('step', 'quiz')
            ->call('selectAnswer', $question->id, $correctOption->id)
            ->call('submit')
            ->assertSet('step', 'thanks');

        $respondent = LmsRespondent::where('course_id', $course->id)->where('name', 'Tamu Eksternal')->first();
        $this->assertNotNull($respondent);
        $this->assertNull($respondent->user_id, 'publik tanpa NIP matching tidak boleh ke-link ke user manapun');

        $this->assertDatabaseHas('lms_quiz_results', [
            'meeting_quiz_id' => $meetingQuiz->id,
            'respondent_id' => $respondent->id,
            'total_score' => 100,
        ]);
    }

    public function test_graduation_requirement_quiz_redirects_to_feedback_after_submit(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'correctOption' => $correctOption, 'course' => $course] = $this->makePublishedCourseWithQuiz();
        $meetingQuiz->update(['is_graduation_requirement' => true]);
        $company = $this->makeCompany();

        $question = $meetingQuiz->quiz->questions->first();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Kelulusan')
            ->set('nip', 'GUEST-00010')
            ->set('company_id', $company->id)
            ->set('unit', 'Perawat - IGD')
            ->call('startQuiz')
            ->call('selectAnswer', $question->id, $correctOption->id)
            ->call('submit')
            ->assertRedirect(route('lms.public.feedback', ['token' => $course->feedback_public_token]));
    }

    public function test_non_graduation_requirement_quiz_stays_on_thanks_page(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'correctOption' => $correctOption] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();

        $question = $meetingQuiz->quiz->questions->first();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Biasa')
            ->set('nip', 'GUEST-00011')
            ->set('company_id', $company->id)
            ->set('unit', 'Perawat - IGD')
            ->call('startQuiz')
            ->call('selectAnswer', $question->id, $correctOption->id)
            ->call('submit')
            ->assertNoRedirect()
            ->assertSet('step', 'thanks');
    }

    public function test_quiz_identity_form_requires_nip(): void
    {
        ['meetingQuiz' => $meetingQuiz] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Tamu Tanpa NIP')
            ->set('company_id', $company->id)
            ->set('unit', 'Perawat - IGD')
            ->call('startQuiz')
            ->assertHasErrors(['nip' => 'required'])
            ->assertSet('step', 'identity');
    }

    public function test_feedback_page_not_found_for_invalid_token(): void
    {
        Livewire::test(PublicFeedbackPage::class, ['token' => 'nonexistent'])
            ->assertSet('notFound', true);
    }

    public function test_feedback_closed_when_single_day_course_date_has_passed(): void
    {
        ['course' => $course, 'meeting' => $meeting] = $this->makePublishedCourseWithQuiz();
        $meeting->update(['start_date' => now()->subDays(2)->toDateString(), 'end_date' => now()->subDays(2)->toDateString()]);
        $course->refresh();

        Livewire::test(PublicFeedbackPage::class, ['token' => $course->feedback_public_token])
            ->assertSet('closed', true);
    }

    public function test_feedback_open_when_multi_meeting_course_last_session_is_today(): void
    {
        ['course' => $course, 'meeting' => $firstMeeting] = $this->makePublishedCourseWithQuiz();
        $firstMeeting->update(['start_date' => now()->subDays(2)->toDateString(), 'end_date' => now()->subDays(2)->toDateString()]);

        // Pertemuan KEDUA (terakhir) hari ini -> umpan balik course masih harus terbuka,
        // walau pertemuan pertama sudah lewat. course->end_date disinkron ke pertemuan
        // TERAKHIR (LmsMeetings::booted()), bukan yang pertama.
        LmsMeetings::create([
            'course_id' => $course->id,
            'title' => 'Sesi 2',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        $course->refresh();

        Livewire::test(PublicFeedbackPage::class, ['token' => $course->feedback_public_token])
            ->assertSet('closed', false);
    }

    public function test_feedback_cannot_submit_with_unanswered_question(): void
    {
        ['course' => $course] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();

        LmsFeedbackQuestion::create(['question' => 'Bagaimana kualitas materi?', 'sort_order' => 1, 'is_active' => true]);
        LmsFeedbackQuestion::create(['question' => 'Bagaimana kualitas fasilitator?', 'sort_order' => 2, 'is_active' => true]);

        Livewire::test(PublicFeedbackPage::class, ['token' => $course->feedback_public_token])
            ->set('name', 'Tamu Feedback')
            ->set('nip', 'GUEST-00003')
            ->set('company_id', $company->id)
            ->set('unit', 'HRD')
            ->call('submit')
            ->assertHasErrors('scores')
            ->assertSet('step', 'form');

        $this->assertDatabaseMissing('lms_course_feedbacks', ['course_id' => $course->id]);
    }

    public function test_public_can_submit_feedback(): void
    {
        ['course' => $course] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();

        $question = LmsFeedbackQuestion::create([
            'question' => 'Bagaimana kualitas materi?',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Livewire::test(PublicFeedbackPage::class, ['token' => $course->feedback_public_token])
            ->set('name', 'Tamu Feedback')
            ->set('nip', 'GUEST-00002')
            ->set('company_id', $company->id)
            ->set('unit', 'HRD')
            ->call('rate', $question->id, 5)
            ->set('feedback', 'Sangat baik.')
            ->call('submit')
            ->assertSet('step', 'thanks');

        $this->assertDatabaseHas('lms_course_feedbacks', [
            'course_id' => $course->id,
            'rating' => 5,
            'feedback' => 'Sangat baik.',
        ]);
    }

    /**
     * Regresi bug produksi: respondent publik yang NIP-nya kebetulan cocok ke karyawan asli
     * (user_id ke-link otomatis) submit kuis LALU umpan balik di course yang sama — dulu
     * crash Integrity constraint violation (unique lama cuma course_id+user_id, row kedua
     * nabrak row pertama). Sekarang harus sukses dua-duanya, jadi 2 baris respondent terpisah
     * (kategori kuis & umpan_balik), bukan 1 baris yang dipakai bareng.
     */
    public function test_public_respondent_matching_real_user_can_submit_both_quiz_and_feedback(): void
    {
        ['meetingQuiz' => $meetingQuiz, 'correctOption' => $correctOption, 'course' => $course] = $this->makePublishedCourseWithQuiz();
        $company = $this->makeCompany();
        $employee = User::factory()->create(['nip' => 'MATCH-00001', 'company_id' => $company->id]);
        $question = $meetingQuiz->quiz->questions->first();

        Livewire::test(PublicQuizPage::class, ['token' => $meetingQuiz->public_token])
            ->set('name', 'Nama Beda Dari Karyawan')
            ->set('nip', 'MATCH-00001')
            ->set('company_id', $company->id)
            ->set('unit', 'IGD')
            ->call('startQuiz')
            ->call('selectAnswer', $question->id, $correctOption->id)
            ->call('submit')
            ->assertHasNoErrors();

        $feedbackQuestion = LmsFeedbackQuestion::create(['question' => 'Kualitas?', 'sort_order' => 1, 'is_active' => true]);

        Livewire::test(PublicFeedbackPage::class, ['token' => $course->feedback_public_token])
            ->set('name', 'Nama Beda Dari Karyawan')
            ->set('nip', 'MATCH-00001')
            ->set('company_id', $company->id)
            ->set('unit', 'IGD')
            ->call('rate', $feedbackQuestion->id, 4)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 'thanks');

        $respondents = LmsRespondent::where('course_id', $course->id)->where('user_id', $employee->id)->get();
        $this->assertCount(2, $respondents);
        $this->assertEqualsCanonicalizing(
            [LmsRespondent::CATEGORY_KUIS, LmsRespondent::CATEGORY_UMPAN_BALIK],
            $respondents->pluck('category')->all()
        );
    }
}
