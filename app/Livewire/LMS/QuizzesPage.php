<?php

namespace App\Livewire\LMS;

use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsQuizzes;
use App\Services\LMS\QuizService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Kuis" — padanan portal dari App\Filament\Resources\LMS\Quizzes (di /admin/lms/quizzes).
 * Pola persis BankSoalPage: list (tabel desktop + card mobile) + filter kategori + search +
 * modal view (read-only, daftar soal) + hapus dengan guard. Edit BUKAN modal kecil — link ke
 * halaman CreateQuiz (route pelatihan.kuis.edit) supaya bisa mengelola ulang soal & urutannya,
 * bukan cuma ganti judul/kategori.
 *
 * Otorisasi: akses halaman via ViewAny:LmsQuizzes (mount). Per-aksi pakai permission Shield
 * string ('Update:LmsQuizzes' / 'Delete:LmsQuizzes'), Policy LmsQuizzesPolicy sudah ada dari Shield.
 */
#[Layout('components.layout', ['title' => 'Kuis'])]
class QuizzesPage extends Component
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

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:LmsQuizzes'), 403);
    }

    public function getCategoryOptionsProperty()
    {
        return LmsCategories::orderBy('name')->pluck('name', 'id');
    }

    protected function baseQuery()
    {
        $query = LmsQuizzes::query()
            ->with('category')
            ->withCount(['questions', 'meetingUsages']);

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->whereRaw('LOWER(title) LIKE ?', ["%{$lower}%"]);
        }

        return $query;
    }

    public function getQuizzesProperty()
    {
        return $this->baseQuery()->orderByDesc('created_at')->paginate(10);
    }

    public function getViewingQuizProperty()
    {
        if (! $this->viewingId) {
            return null;
        }

        return LmsQuizzes::with(['category', 'questions.options'])->find($this->viewingId);
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
        return Auth::user()->can('Create:LmsQuizzes');
    }

    public function getCanUpdateProperty(): bool
    {
        return Auth::user()->can('Update:LmsQuizzes');
    }

    public function getCanDeleteProperty(): bool
    {
        return Auth::user()->can('Delete:LmsQuizzes');
    }

    public function removeQuiz(string $id, QuizService $service): void
    {
        abort_unless(Auth::user()->can('Delete:LmsQuizzes'), 403);

        try {
            $service->deleteQuiz($id);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Kuis berhasil dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.lms.quizzes-page');
    }
}
