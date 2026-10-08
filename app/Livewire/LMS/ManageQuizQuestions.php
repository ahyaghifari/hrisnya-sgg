<?php

namespace App\Livewire\LMS;

use App\Models\LMS\LmsQuestionBanks;
use App\Models\LMS\LmsQuizzes;
use App\Services\LMS\QuizService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Kelola Soal" kuis — tambah/kurangi/urutkan soal dari Bank Soal (difilter kategori
 * kuis). Judul & kategori diubah di CreateQuiz. Soal baru dibuat di halaman Bank Soal
 * (pelatihan.bank-soal.create), bukan modal.
 */
#[Layout('components.layout', ['title' => 'Kelola Soal Kuis'])]
class ManageQuizQuestions extends Component
{
    public string $quizId;

    /** @var string[] urutan soal terpilih (id LmsQuestionBanks) */
    public array $selectedQuestionIds = [];

    public string $questionSearch = '';

    // ULID mentah (bukan type-hint model), alasan sama seperti CreateQuiz::mount().
    public function mount(string $quiz): void
    {
        abort_unless(Auth::user()->can('Update:LmsQuizzes'), 403);

        $model = LmsQuizzes::findOrFail($quiz);
        $this->quizId = $model->id;
        $this->selectedQuestionIds = $model->questions()->pluck('lms_question_banks.id')->all();
    }

    public function getQuizProperty(): LmsQuizzes
    {
        return LmsQuizzes::findOrFail($this->quizId);
    }

    public function getAvailableQuestionsProperty()
    {
        $query = LmsQuestionBanks::where('category_id', $this->quiz->category_id)
            ->whereNotIn('id', $this->selectedQuestionIds);

        $search = trim($this->questionSearch);
        if ($search !== '') {
            $query->whereRaw('LOWER(question) LIKE ?', ['%' . mb_strtolower($search) . '%']);
        }

        return $query->orderByDesc('created_at')->limit(20)->get();
    }

    /** Soal terpilih, urut sesuai $selectedQuestionIds (bukan urutan hasil query). */
    public function getSelectedQuestionsProperty()
    {
        $questions = LmsQuestionBanks::whereIn('id', $this->selectedQuestionIds)->get()->keyBy('id');

        return collect($this->selectedQuestionIds)->map(fn ($id) => $questions->get($id))->filter();
    }

    public function getCanCreateQuestionProperty(): bool
    {
        return Auth::user()->can('Create:LmsQuestionBanks');
    }

    public function addQuestion(string $id): void
    {
        if (! in_array($id, $this->selectedQuestionIds, true)) {
            $this->selectedQuestionIds[] = $id;
        }
    }

    public function removeQuestion(string $id): void
    {
        $this->selectedQuestionIds = array_values(array_diff($this->selectedQuestionIds, [$id]));
    }

    public function moveQuestionUp(int $index): void
    {
        if ($index > 0 && isset($this->selectedQuestionIds[$index])) {
            $this->swapQuestions($index, $index - 1);
        }
    }

    public function moveQuestionDown(int $index): void
    {
        if (isset($this->selectedQuestionIds[$index + 1])) {
            $this->swapQuestions($index, $index + 1);
        }
    }

    private function swapQuestions(int $a, int $b): void
    {
        [$this->selectedQuestionIds[$a], $this->selectedQuestionIds[$b]]
            = [$this->selectedQuestionIds[$b], $this->selectedQuestionIds[$a]];
    }

    public function save(QuizService $service): void
    {
        $this->validate(
            ['selectedQuestionIds' => ['required', 'array', 'min:1']],
            [],
            ['selectedQuestionIds' => 'daftar soal'],
        );

        $quiz = $this->quiz;
        $service->updateQuiz($quiz->id, ['category_id' => $quiz->category_id, 'title' => $quiz->title], $this->selectedQuestionIds);

        Notification::make()->title('Soal kuis "' . $quiz->title . '" berhasil diperbarui')->success()->send();

        $this->redirect(route('pelatihan.kuis'), navigate: true);
    }

    public function render()
    {
        return view('livewire.lms.manage-quiz-questions');
    }
}
