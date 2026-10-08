<?php

namespace App\Livewire\LMS;

use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsQuizzes;
use App\Services\LMS\QuizService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Buat Kuis" / "Ubah Kuis" — 1 komponen dipakai untuk keduanya (route
 * pelatihan.kuis.create tanpa parameter = mode buat, pelatihan.kuis.edit dengan {quiz} = mode
 * ubah). Hanya judul & kategori; soal-soalnya dikelola di halaman terpisah ManageQuizQuestions
 * (route pelatihan.kuis.soal). Setelah kuis dibuat, user langsung diarahkan ke sana.
 *
 * Kategori dikunci saat ubah: soal kuis difilter per kategori, ganti kategori = soal lama
 * tidak relevan.
 */
#[Layout('components.layout', ['title' => 'Kuis'])]
class CreateQuiz extends Component
{
    public ?string $editingQuizId = null;

    public ?string $categoryId = null;
    public string $title = '';

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
        } else {
            abort_unless(Auth::user()->can('Create:LmsQuizzes'), 403);
        }
    }

    public function getCategoryOptionsProperty()
    {
        return LmsCategories::orderBy('name')->pluck('name', 'id');
    }

    public function save(QuizService $service): void
    {
        $validated = $this->validate([
            'categoryId' => ['required', 'exists:lms_categories,id'],
            'title' => ['required', 'string', 'max:255'],
        ], [], [
            'categoryId' => 'kategori',
            'title' => 'judul kuis',
        ]);

        $data = ['category_id' => $validated['categoryId'], 'title' => $validated['title']];

        if ($this->editingQuizId) {
            $quiz = LmsQuizzes::findOrFail($this->editingQuizId);
            // Kategori terkunci saat ubah; pakai kategori tersimpan, bukan input klien.
            $data['category_id'] = $quiz->category_id;
            $ids = $quiz->questions()->pluck('lms_question_banks.id')->all();
            $quiz = $service->updateQuiz($quiz->id, $data, $ids);
            Notification::make()->title('Kuis "' . $quiz->title . '" berhasil diperbarui')->success()->send();
            $this->redirect(route('pelatihan.kuis'), navigate: true);

            return;
        }

        $quiz = $service->create($data, []);
        Notification::make()->title('Kuis "' . $quiz->title . '" dibuat, tambahkan soalnya')->success()->send();
        $this->redirect(route('pelatihan.kuis.soal', $quiz), navigate: true);
    }

    public function render()
    {
        return view('livewire.lms.create-quiz');
    }
}
