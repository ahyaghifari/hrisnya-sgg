<?php

namespace App\Livewire\LMS;

use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsRespondent;
use App\Models\Master\Companies;
use App\Services\LMS\CourseService;
use App\Services\LMS\RespondentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class PublicFeedbackPage extends Component
{
    public string $token;

    public ?LmsCourses $course = null;

    public bool $notFound = false;

    public bool $closed = false;

    /** @var 'form'|'thanks' */
    public string $step = 'form';

    public string $name = '';
    public string $nip = '';
    public ?string $company_id = null;
    public string $unit = '';

    public array $questions = [];

    /** @var array<string, int> question_id => score (1-5) */
    public array $scores = [];

    public string $feedback = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $course = LmsCourses::where('feedback_public_token', $token)->first();

        if (! $course || $course->status !== LsmCourseStatus::Published->value) {
            $this->notFound = true;

            return;
        }

        $this->course = $course;

        if ($this->isPastCutoff()) {
            $this->closed = true;

            return;
        }

        $this->questions = app(CourseService::class)->getFeedbackQuestions()->all();
    }

    /**
     * Umpan balik melekat ke course, bukan 1 pertemuan tertentu — batasnya dari
     * course->end_date, yang otomatis tersinkron dari meetings (LmsMeetings::booted()):
     * course 1 hari -> end_date = tanggal hari itu; course banyak pertemuan -> end_date =
     * tanggal pertemuan TERAKHIR. Jadi tidak perlu logic terpisah untuk 2 kasus itu.
     */
    protected function isPastCutoff(): bool
    {
        $cutoff = $this->course->end_date ?? $this->course->start_date;

        return $cutoff && Carbon::today()->gt(Carbon::parse($cutoff));
    }

    public function getCompaniesProperty(): Collection
    {
        return Companies::where('is_active', true)->orderBy('name')->get();
    }

    public function rate(string $questionId, int $score): void
    {
        $this->scores[$questionId] = $score;
    }

    public function submit(): void
    {
        abort_if($this->isPastCutoff(), 403);

        $this->validate([
            'name' => 'required|string|max:255',
            'nip' => 'required|string|max:50',
            'company_id' => 'required|exists:m_companies,id',
            'unit' => 'required|string|max:255',
        ]);

        $missing = collect($this->questions)
            ->pluck('id')
            ->reject(fn ($id) => filled($this->scores[$id] ?? null));

        if ($missing->isNotEmpty()) {
            $this->addError('scores', 'Masih ada pertanyaan yang belum dinilai.');

            return;
        }

        $respondent = app(RespondentService::class)->resolveForPublic($this->course->id, [
            'name' => $this->name,
            'nip' => $this->nip ?: null,
            'company_id' => $this->company_id,
            'unit' => $this->unit,
        ], LmsRespondent::CATEGORY_UMPAN_BALIK);

        $answers = collect($this->scores)
            ->map(fn ($score, $questionId) => ['question_id' => $questionId, 'score' => $score])
            ->values()
            ->all();

        app(CourseService::class)->storeFeedback([
            'respondent_id' => $respondent->id,
            'course_id' => $this->course->id,
            'answers' => $answers,
            'feedback' => $this->feedback ?: null,
        ]);

        $this->step = 'thanks';
    }

    public function render()
    {
        return view('livewire.lms.public-feedback-page')
            ->layout('components.public-layout', ['title' => 'Umpan Balik']);
    }
}
