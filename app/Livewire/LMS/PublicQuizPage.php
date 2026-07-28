<?php

namespace App\Livewire\LMS;

use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsRespondent;
use App\Models\Master\Companies;
use App\Services\LMS\QuizService;
use App\Services\LMS\RespondentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class PublicQuizPage extends Component
{
    public string $token;

    public ?LmsMeetingQuizzes $meetingQuiz = null;

    public bool $notFound = false;

    public bool $closed = false;

    /** @var 'identity'|'quiz'|'thanks' */
    public string $step = 'identity';

    public string $name = '';
    public string $nip = '';
    public ?string $company_id = null;
    public string $unit = '';

    public array $questions = [];

    /** @var array<string, string> question_id => option_id */
    public array $answers = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        $meetingQuiz = LmsMeetingQuizzes::with('meeting.course', 'quiz')
            ->where('public_token', $token)
            ->first();

        if (! $meetingQuiz || $meetingQuiz->meeting->course->status !== LsmCourseStatus::Published->value) {
            $this->notFound = true;

            return;
        }

        $this->meetingQuiz = $meetingQuiz;

        if ($this->isPastCutoff()) {
            $this->closed = true;

            return;
        }

        $this->restoreFromSession();
    }

    /**
     * Kuis melekat ke 1 pertemuan tertentu (bukan course), jadi batasnya selalu tanggal
     * pertemuan itu sendiri — otomatis benar baik course 1 hari maupun banyak pertemuan,
     * karena tiap kuis memang cuma pernah dipasang di 1 pertemuan.
     */
    protected function isPastCutoff(): bool
    {
        $meeting = $this->meetingQuiz->meeting;
        $cutoff = $meeting->end_date ?? $meeting->start_date;

        return $cutoff && Carbon::today()->gt(Carbon::parse($cutoff));
    }

    public function getCompaniesProperty(): Collection
    {
        return Companies::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Identitas + progres jawaban disimpan di session (keyed per token kuis) supaya
     * refresh/tutup-buka tab tidak menghilangkan isian — beda dari keputusan awal
     * (state cuma di memori komponen), diubah atas permintaan user karena reload di
     * tengah kuis bikin peserta harus isi ulang dari nol.
     */
    protected function sessionKey(): string
    {
        return 'public_quiz.' . $this->token;
    }

    protected function restoreFromSession(): void
    {
        $state = Session::get($this->sessionKey());

        if (! $state) {
            return;
        }

        $this->step = $state['step'] ?? 'identity';
        $this->name = $state['name'] ?? '';
        $this->nip = $state['nip'] ?? '';
        $this->company_id = $state['company_id'] ?? null;
        $this->unit = $state['unit'] ?? '';
        $this->questions = $state['questions'] ?? [];
        $this->answers = $state['answers'] ?? [];
    }

    protected function persistToSession(): void
    {
        Session::put($this->sessionKey(), [
            'step' => $this->step,
            'name' => $this->name,
            'nip' => $this->nip,
            'company_id' => $this->company_id,
            'unit' => $this->unit,
            'questions' => $this->questions,
            'answers' => $this->answers,
        ]);
    }

    public function startQuiz(): void
    {
        abort_if($this->isPastCutoff(), 403);

        $this->validate([
            'name' => 'required|string|max:255',
            'nip' => 'required|string|max:50',
            'company_id' => 'required|exists:m_companies,id',
            'unit' => 'required|string|max:255',
        ]);

        $quiz = app(QuizService::class)->getQuizDetail($this->meetingQuiz->id, excludeEssay: true);

        $this->questions = collect($quiz['questions'] ?? [])
            ->where('type', 'multiple_choice')
            ->values()
            ->all();

        $this->step = 'quiz';

        $this->persistToSession();
    }

    public function selectAnswer(string $questionId, string $optionId): void
    {
        $this->answers[$questionId] = $optionId;

        $this->persistToSession();
    }

    public function submit(): void
    {
        abort_if($this->isPastCutoff(), 403);

        $missing = collect($this->questions)
            ->pluck('id')
            ->reject(fn ($id) => filled($this->answers[$id] ?? null));

        if ($missing->isNotEmpty()) {
            $this->addError('answers', 'Masih ada soal yang belum dijawab.');

            return;
        }

        $respondent = app(RespondentService::class)->resolveForPublic($this->meetingQuiz->meeting->course_id, [
            'name' => $this->name,
            'nip' => $this->nip ?: null,
            'company_id' => $this->company_id,
            'unit' => $this->unit,
        ], LmsRespondent::CATEGORY_KUIS);

        $payload = collect($this->answers)
            ->map(fn ($optionId, $questionId) => ['question_id' => $questionId, 'answer_id' => $optionId])
            ->values()
            ->all();

        app(QuizService::class)->submitQuiz($this->meetingQuiz->id, $payload, $respondent->id);

        Session::forget($this->sessionKey());

        // Kuis penentu kelulusan -> lanjut langsung ke umpan balik publik (kalau course
        // memang requires_feedback dan sudah punya token) alih-alih berhenti di halaman
        // terima kasih kuis. Kuis biasa (bukan penentu kelulusan) tetap berhenti di sini.
        $course = $this->meetingQuiz->meeting->course;

        if ($this->meetingQuiz->is_graduation_requirement && $course->requires_feedback && $course->feedback_public_token) {
            $this->redirect(route('lms.public.feedback', ['token' => $course->feedback_public_token]), navigate: true);

            return;
        }

        $this->step = 'thanks';
    }

    public function render()
    {
        return view('livewire.lms.public-quiz-page')
            ->layout('components.public-layout', ['title' => 'Kuis']);
    }
}
