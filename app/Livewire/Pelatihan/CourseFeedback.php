<?php

namespace App\Livewire\Pelatihan;

use App\Models\LMS\LmsCourseFeedbacks;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsRespondent;
use App\Repositories\LMS\CourseUserRepository;
use App\Services\LMS\CourseService;
use App\Services\LMS\RespondentService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CourseFeedback extends Component
{
    public LmsCourses $course;

    public bool $blocked = false;

    public bool $locked = false;

    public bool $alreadySubmitted = false;

    public string $respondentId;

    public array $questions = [];

    /** @var array<string, int> question_id => score (1-5) */
    public array $scores = [];

    public string $feedback = '';

    public function mount(LmsCourses $course): void
    {
        $course->load('category');

        $this->course = $course;

        abort_unless($course->requires_feedback, 404);

        $courseUser = app(CourseUserRepository::class)->getByUserIdAndCourseId(Auth::id(), $course->id);

        if (! $courseUser) {
            $this->blocked = true;

            return;
        }

        $this->respondentId = app(RespondentService::class)
            ->resolveForEmployee(Auth::id(), $course->id, LmsRespondent::CATEGORY_UMPAN_BALIK)
            ->id;

        if (LmsCourseFeedbacks::where('course_id', $course->id)->where('respondent_id', $this->respondentId)->exists()) {
            $this->alreadySubmitted = true;

            return;
        }

        // Gate kunci — mirror QuizAttempt::mount(), admin buka manual lewat tombol "Buka Umpan
        // Balik" di halaman detail pelatihan. Akses publik lewat QR/token (PublicFeedbackPage)
        // TIDAK kena gate ini, sengaja beda jalur.
        if (! $course->feedback_unlocked) {
            $this->locked = true;

            return;
        }

        $this->questions = app(CourseService::class)->getFeedbackQuestions()->all();
    }

    // ponytail: sama persis dengan TrainingDetail::getAccentProperty()/QuizAttempt — hash 4
    // baris, duplikasi lebih murah daripada trait/service baru untuk 4 pemakaian.
    public function getAccentProperty(): string
    {
        $palette = ['navy', 'green', 'rust', 'teal', 'gold'];
        $categoryName = $this->course->category?->name;

        if (! $categoryName) {
            return $palette[0];
        }

        return $palette[crc32($categoryName) % count($palette)];
    }

    public function rate(string $questionId, int $score): void
    {
        $this->scores[$questionId] = $score;
    }

    public function submit(): void
    {
        $missing = collect($this->questions)
            ->pluck('id')
            ->reject(fn ($id) => filled($this->scores[$id] ?? null));

        if ($missing->isNotEmpty()) {
            $this->addError('scores', 'Masih ada pertanyaan yang belum dinilai.');

            return;
        }

        $answers = collect($this->scores)
            ->map(fn ($score, $questionId) => ['question_id' => $questionId, 'score' => $score])
            ->values()
            ->all();

        app(CourseService::class)->storeFeedback([
            'respondent_id' => $this->respondentId,
            'course_id' => $this->course->id,
            'answers' => $answers,
            'feedback' => $this->feedback ?: null,
        ]);

        $this->questions = [];
        $this->alreadySubmitted = true;

        Notification::make()
            ->title('Umpan balik berhasil dikirim')
            ->success()
            ->send();

        // View: 5 detik kemudian balik ke detail pelatihan.
        $this->dispatch('feedback-submitted', url: route('pelatihan.show', ['course' => $this->course->id]));
    }

    public function render()
    {
        return view('livewire.pelatihan.course-feedback')
            ->layout('components.layout', ['title' => 'Umpan Balik']);
    }
}
