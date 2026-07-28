<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Enum\QuestionType;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsQuestionBanks;
use App\Services\LMS\QuestionBankService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Bank Soal" — padanan portal dari App\Filament\Resources\LMS\QuestionBanks\QuestionBanksResource
 * (di /admin/lms/question-banks). Sengaja di-built ulang di portal (bukan reuse komponen Filament)
 * supaya konsisten dengan halaman LMS lain (Pelatihan, Kategori, Pemateri).
 *
 * Fitur: list (tabel desktop + card mobile) + filter kategori + search + modal view (read-only)
 * + modal edit (1 soal) + hapus (dengan konfirmasi wire:confirm). Create multi-soal ada di
 * halaman terpisah CreateBankSoal (route pelatihan.bank-soal.create).
 *
 * Pola list persis LmsCoursesPage (TANPA WithPagination, lihat komentar di file itu soal dobel
 * segment). Pola modal edit persis TrainersPage ($editingId + form fields + saveEdit()).
 *
 * Otorisasi: akses halaman via SISTEM_LMS (mount). Per-aksi pakai permission Shield string
 * ('Update:LmsQuestionBanks' / 'Delete:LmsQuestionBanks') — Policy LmsQuestionBanksPolicy
 * di-generate Shield, format permission sudah di-rename dari 'QuestionBanks' → 'LmsQuestionBanks'
 * (migration 2026_07_14_150000_rename_question_banks_permissions.php).
 */
#[Layout('components.layout', ['title' => 'Bank Soal'])]
class BankSoalPage extends Component
{
    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public string $search = '';

    #[Url]
    public ?string $categoryId = null;

    // Modal view (read-only)
    public ?string $viewingId = null;

    // Modal edit
    public bool $showEditModal = false;
    public ?string $editingId = null;
    public ?string $formCategoryId = null;
    public string $formQuestion = '';
    public string $formType = 'multiple_choice';
    public string $formMinScore = '';
    public string $formMaxScore = '';
    public array $formOptions = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
    }

    public function getCategoryOptionsProperty()
    {
        return LmsCategories::orderBy('name')->pluck('name', 'id');
    }

    protected function baseQuery()
    {
        $query = LmsQuestionBanks::query()
            ->with(['category', 'options'])
            ->withCount('quizzes');

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        $search = trim($this->search);
        // Kolom `question` simpan HTML (Trix/RichEditor) — search LIKE biasa masih jalan, HTML
        // tag ikut dicari tapi praktis tidak mengganggu hasil. Alternatif (kolom question_plain
        // + index) butuh migration, sengaja belum ditambah.
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->whereRaw('LOWER(question) LIKE ?', ["%{$lower}%"]);
        }

        return $query;
    }

    public function getSoalProperty()
    {
        return $this->baseQuery()->orderByDesc('created_at')->paginate(10)->appends([
            'search' => $this->search,
            'categoryId' => $this->categoryId,
        ]);
    }

    public function getViewingSoalProperty()
    {
        if (! $this->viewingId) {
            return null;
        }

        return LmsQuestionBanks::with(['category', 'options'])->find($this->viewingId);
    }

    public function openViewModal(string $id): void
    {
        $this->viewingId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewingId = null;
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->can('Create:LmsQuestionBanks');
    }

    public function getCanUpdateProperty(): bool
    {
        return Auth::user()->can('Update:LmsQuestionBanks');
    }

    public function getCanDeleteProperty(): bool
    {
        return Auth::user()->can('Delete:LmsQuestionBanks');
    }

    public function openEditModal(string $id): void
    {
        $question = LmsQuestionBanks::with('options')->findOrFail($id);

        abort_unless(Auth::user()->can('Update:LmsQuestionBanks'), 403);

        $this->editingId = $question->id;
        $this->formCategoryId = $question->category_id;
        $this->formQuestion = (string) $question->question;
        // `type` tidak di-cast ke enum di model (akses manual via QuestionType::from()) —
        // lihat catatan di LmsQuestionBanks, QuizService juga pakai string literal 'essay'.
        $typeEnum = QuestionType::from($question->type);
        $this->formType = $typeEnum->value;
        $this->formMinScore = $typeEnum === QuestionType::Essay ? (string) $question->min_score : '';
        $this->formMaxScore = $typeEnum === QuestionType::Essay ? (string) $question->max_score : '';

        $this->formOptions = $question->options->map(fn ($opt) => [
            'text' => (string) $opt->option_text,
            'is_correct' => (bool) $opt->is_correct,
        ])->toArray();

        // Minimal 2 opsi kalau multiple_choice (dan belum punya opsi sama sekali)
        if ($this->formType === QuestionType::MultipleChoice->value && count($this->formOptions) < 2) {
            while (count($this->formOptions) < 2) {
                $this->formOptions[] = ['text' => '', 'is_correct' => false];
            }
        }

        $this->resetErrorBag();
        $this->showEditModal = true;
        // Tutup modal view kalau sedang terbuka
        $this->viewingId = null;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingId = null;
    }

    public function addOption(): void
    {
        if (count($this->formOptions) >= 10) {
            return;
        }
        $this->formOptions[] = ['text' => '', 'is_correct' => false];
    }

    public function removeOption(int $index): void
    {
        if (count($this->formOptions) <= 2) {
            return;
        }
        unset($this->formOptions[$index]);
        $this->formOptions = array_values($this->formOptions);
    }

    /**
     * Tandai satu opsi sebagai jawaban benar + uncheck opsi lain — radio behavior untuk
     * multiple_choice. Input di blade type="radio" (native, di-group via `name`) supaya browser
     * yang menjamin cuma 1 opsi ter-check.
     */
    public function setCorrectOption(int $optionIndex): void
    {
        if (! isset($this->formOptions[$optionIndex])) {
            return;
        }
        foreach ($this->formOptions as $i => $opt) {
            $this->formOptions[$i]['is_correct'] = false;
        }
        $this->formOptions[$optionIndex]['is_correct'] = true;
    }

    public function saveEdit(QuestionBankService $service): void
    {
        abort_unless(Auth::user()->can('Update:LmsQuestionBanks'), 403);

        $rules = [
            'formCategoryId' => 'required|exists:lms_categories,id',
            'formQuestion' => 'required|string',
            'formType' => 'required|in:' . implode(',', array_column(QuestionType::cases(), 'value')),
            'formMinScore' => 'nullable|integer|min:0|max:100',
            'formMaxScore' => 'nullable|integer|min:0|max:100',
            'formOptions' => ['array'],
            'formOptions.*.text' => 'required|string',
            'formOptions.*.is_correct' => 'boolean',
        ];

        if ($this->formType === QuestionType::Essay->value) {
            $rules['formMaxScore'] = 'required|integer|min:1|max:100';
            $rules['formMinScore'] = 'nullable|integer|min:0|max:100';
        }

        $validated = $this->validate($rules, [], [
            'formCategoryId' => 'kategori',
            'formQuestion' => 'pertanyaan',
            'formType' => 'tipe soal',
            'formMinScore' => 'skor minimal',
            'formMaxScore' => 'skor maksimal',
            'formOptions' => 'opsi jawaban',
            'formOptions.*.text' => 'teks opsi',
        ]);

        $service->update($this->editingId, [
            'category_id' => $validated['formCategoryId'],
            'question' => $validated['formQuestion'],
            'type' => $validated['formType'],
            'min_score' => $validated['formMinScore'] ?? 0,
            'max_score' => $validated['formMaxScore'] ?? 0,
            'options' => $validated['formType'] === QuestionType::MultipleChoice->value
                ? $validated['formOptions']
                : [],
        ]);

        $this->showEditModal = false;
        $this->editingId = null;

        Notification::make()->title('Soal berhasil diperbarui')->success()->send();
    }

    public function removeSoal(string $id, QuestionBankService $service): void
    {
        abort_unless(Auth::user()->can('Delete:LmsQuestionBanks'), 403);

        try {
            $service->delete($id);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Soal berhasil dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.lms.bank-soal-page');
    }
}
