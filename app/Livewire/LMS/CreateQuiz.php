<?php

namespace App\Livewire\LMS;

use App\Enum\QuestionType;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\LMS\LmsQuizzes;
use App\Services\LMS\QuestionBankService;
use App\Services\LMS\QuizService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Buat Kuis" / "Ubah Kuis" — 1 komponen dipakai untuk keduanya (route
 * pelatihan.kuis.create tanpa parameter = mode buat, pelatihan.kuis.edit dengan route-model
 * binding {quiz} = mode ubah) supaya edit tidak cuma ganti judul/kategori lewat modal kecil,
 * tapi bisa juga mengelola ulang soal & urutannya persis seperti saat membuat.
 *
 * Kategori wajib dipilih dulu, baru bisa memilih soal-soal dari Bank Soal (difilter kategori
 * yang sama). Soal terpilih ditampilkan sebagai repeater (pola sama seperti CreateBankSoal)
 * supaya bisa diurutkan (naik/turun) & dihapus dari pilihan — urutan disimpan ke kolom
 * `lms_quiz_questions.sort_order` lewat QuizService::create()/updateQuiz().
 *
 * "+ Buat Soal Baru": modal berisi 1 form soal (persis 1 baris repeater CreateBankSoal — tipe,
 * pertanyaan Trix, opsi radio-benar) supaya user tidak perlu pindah halaman ke Bank Soal kalau
 * soal yang dicari belum ada. Soal baru langsung tersimpan ke Bank Soal (QuestionBankService::
 * createOne(), kategori otomatis ikut kategori kuis) lalu langsung masuk ke daftar terpilih.
 */
#[Layout('components.layout', ['title' => 'Kuis'])]
class CreateQuiz extends Component
{
    public ?string $editingQuizId = null;

    public ?string $categoryId = null;
    public string $title = '';

    /** @var string[] urutan soal terpilih (array value = id LmsQuestionBanks) */
    public array $selectedQuestionIds = [];

    public string $questionSearch = '';

    // Modal "+ Buat Soal Baru"
    public bool $showNewQuestionModal = false;
    public string $newType = 'multiple_choice';
    public string $newQuestion = '';
    public string $newMinScore = '';
    public string $newMaxScore = '';
    public array $newOptions = [];

    /**
     * $quiz sengaja `?string` (ULID mentah), BUKAN type-hint model — Livewire full-page
     * component dengan mount() bertipe model implicit-binding menyimpan snapshot yang rusak
     * saat parameter route TIDAK ada sama sekali (kasus rute pelatihan.kuis.create tanpa
     * {quiz}), bikin request lanjutan (set/call) di test error "array offset on null".
     * Resolve manual di sini supaya rute create (tanpa parameter) & edit (dengan parameter)
     * bisa berbagi 1 komponen yang sama dengan aman.
     */
    public function mount(?string $quiz = null): void
    {
        if ($quiz) {
            $model = LmsQuizzes::findOrFail($quiz);

            abort_unless(Auth::user()->can('Update:LmsQuizzes'), 403);

            $this->editingQuizId = $model->id;
            $this->categoryId = $model->category_id;
            $this->title = $model->title;
            $this->selectedQuestionIds = $model->questions()->pluck('lms_question_banks.id')->all();
        } else {
            abort_unless(Auth::user()->can('Create:LmsQuizzes'), 403);
        }
    }

    public function getCategoryOptionsProperty()
    {
        return LmsCategories::orderBy('name')->pluck('name', 'id');
    }

    public function updatedCategoryId(): void
    {
        // Ganti kategori — soal terpilih dari kategori lama sudah tidak relevan.
        $this->selectedQuestionIds = [];
    }

    public function getAvailableQuestionsProperty()
    {
        if (! $this->categoryId) {
            return collect();
        }

        $query = LmsQuestionBanks::where('category_id', $this->categoryId)
            ->whereNotIn('id', $this->selectedQuestionIds);

        $search = trim($this->questionSearch);
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->whereRaw('LOWER(question) LIKE ?', ["%{$lower}%"]);
        }

        return $query->orderByDesc('created_at')->limit(20)->get();
    }

    /** Soal terpilih, urut sesuai $selectedQuestionIds (bukan urutan hasil query). */
    public function getSelectedQuestionsProperty()
    {
        if (empty($this->selectedQuestionIds)) {
            return collect();
        }

        $questions = LmsQuestionBanks::whereIn('id', $this->selectedQuestionIds)->get()->keyBy('id');

        return collect($this->selectedQuestionIds)
            ->map(fn ($id) => $questions->get($id))
            ->filter();
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
        if ($index <= 0 || ! isset($this->selectedQuestionIds[$index])) {
            return;
        }
        $this->swapQuestions($index, $index - 1);
    }

    public function moveQuestionDown(int $index): void
    {
        if (! isset($this->selectedQuestionIds[$index + 1])) {
            return;
        }
        $this->swapQuestions($index, $index + 1);
    }

    private function swapQuestions(int $a, int $b): void
    {
        [$this->selectedQuestionIds[$a], $this->selectedQuestionIds[$b]]
            = [$this->selectedQuestionIds[$b], $this->selectedQuestionIds[$a]];
    }

    public function getCanCreateQuestionProperty(): bool
    {
        return Auth::user()->can('Create:LmsQuestionBanks');
    }

    public function openNewQuestionModal(): void
    {
        abort_unless($this->canCreateQuestion, 403);

        if (! $this->categoryId) {
            return;
        }

        $this->newType = QuestionType::MultipleChoice->value;
        $this->newQuestion = '';
        $this->newMinScore = '';
        $this->newMaxScore = '';
        $this->newOptions = [
            ['text' => '', 'is_correct' => false],
            ['text' => '', 'is_correct' => false],
        ];
        $this->resetErrorBag();
        $this->showNewQuestionModal = true;
    }

    public function closeNewQuestionModal(): void
    {
        $this->showNewQuestionModal = false;
    }

    public function updatedNewType(string $value): void
    {
        if ($value === QuestionType::MultipleChoice->value) {
            $this->newMinScore = '';
            $this->newMaxScore = '';
            if (count($this->newOptions) < 2) {
                while (count($this->newOptions) < 2) {
                    $this->newOptions[] = ['text' => '', 'is_correct' => false];
                }
            }
        } else {
            $this->newOptions = [];
        }
    }

    public function addNewOption(): void
    {
        if (count($this->newOptions) >= 10) {
            return;
        }
        $this->newOptions[] = ['text' => '', 'is_correct' => false];
    }

    public function removeNewOption(int $index): void
    {
        if (! isset($this->newOptions[$index]) || count($this->newOptions) <= 2) {
            return;
        }
        unset($this->newOptions[$index]);
        $this->newOptions = array_values($this->newOptions);
    }

    public function setNewCorrectOption(int $index): void
    {
        if (! isset($this->newOptions[$index])) {
            return;
        }
        foreach ($this->newOptions as $i => $opt) {
            $this->newOptions[$i]['is_correct'] = false;
        }
        $this->newOptions[$index]['is_correct'] = true;
    }

    public function saveNewQuestion(QuestionBankService $service): void
    {
        // "+ Buat Soal Baru" nyimpen langsung ke Bank Soal (bukan cuma Kuis) - butuh
        // Create:LmsQuestionBanks sendiri, bukan cuma Create/Update:LmsQuizzes dari mount().
        abort_unless(Auth::user()->can('Create:LmsQuestionBanks'), 403);

        $validated = $this->validate([
            'newQuestion' => ['required', 'string'],
            'newType' => ['required', 'in:' . implode(',', array_column(QuestionType::creatable(), 'value'))],
            'newMinScore' => ['nullable', 'integer', 'min:0', 'max:100'],
            'newMaxScore' => ['nullable', 'integer', 'min:0', 'max:100'],
            'newOptions' => ['array'],
            'newOptions.*.text' => ['required', 'string'],
            'newOptions.*.is_correct' => ['boolean'],
        ], [], [
            'newQuestion' => 'pertanyaan',
            'newType' => 'tipe soal',
            'newOptions.*.text' => 'teks opsi',
        ]);

        if ($validated['newType'] === QuestionType::Essay->value) {
            if (empty($validated['newMaxScore']) || (int) $validated['newMaxScore'] < 1) {
                $this->addError('newMaxScore', 'Skor maksimal wajib diisi untuk soal esai (minimal 1).');

                return;
            }
        } else {
            $correctCount = collect($validated['newOptions'])->filter(fn ($o) => ! empty($o['is_correct']))->count();
            if ($correctCount < 1) {
                $this->addError('newOptions', 'Minimal 1 opsi harus ditandai sebagai jawaban benar.');

                return;
            }
        }

        $question = $service->createOne([
            'category_id' => $this->categoryId,
            'question' => $validated['newQuestion'],
            'type' => $validated['newType'],
            'min_score' => $validated['newMinScore'] ?? 0,
            'max_score' => $validated['newMaxScore'] ?? 0,
            'options' => $validated['newOptions'],
        ]);

        $this->addQuestion($question->id);
        $this->showNewQuestionModal = false;

        Notification::make()->title('Soal baru berhasil ditambahkan')->success()->send();
    }

    public function save(QuizService $service): void
    {
        $validated = $this->validate([
            'categoryId' => ['required', 'exists:lms_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'selectedQuestionIds' => ['required', 'array', 'min:1'],
        ], [], [
            'categoryId' => 'kategori',
            'title' => 'judul kuis',
            'selectedQuestionIds' => 'daftar soal',
        ]);

        $data = [
            'category_id' => $validated['categoryId'],
            'title' => $validated['title'],
        ];

        if ($this->editingQuizId) {
            $quiz = $service->updateQuiz($this->editingQuizId, $data, $validated['selectedQuestionIds']);
            Notification::make()->title('Kuis "' . $quiz->title . '" berhasil diperbarui')->success()->send();
        } else {
            $quiz = $service->create($data, $validated['selectedQuestionIds']);
            Notification::make()->title('Kuis "' . $quiz->title . '" berhasil dibuat')->success()->send();
        }

        $this->redirect(route('pelatihan.kuis'), navigate: true);
    }

    public function render()
    {
        return view('livewire.lms.create-quiz');
    }
}
