<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\LmsFeedbackQuestion;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Padanan portal dari Filament LmsFeedbackQuestionsResource — CRUD master pertanyaan
 * evaluasi pelatihan (skala tetap 1-5) lewat modal, satu halaman flat sama seperti
 * Kategori Pelatihan (LmsCategoriesPage).
 */
#[Layout('components.layout', ['title' => 'Pertanyaan Evaluasi'])]
class LmsFeedbackQuestionsPage extends Component
{
    public bool $showFormModal = false;
    public ?string $editingId = null;
    public string $formGroupLabel = '';
    public string $formQuestion = '';
    public bool $formIsActive = true;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
    }

    public function getQuestionsProperty()
    {
        return LmsFeedbackQuestion::withCount('answers')
            ->orderBy('sort_order')
            ->get();
    }

    public function getCanCreateQuestionProperty(): bool
    {
        return Auth::user()->can('create', LmsFeedbackQuestion::class);
    }

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', LmsFeedbackQuestion::class), 403);

        $this->editingId = null;
        $this->formGroupLabel = '';
        $this->formQuestion = '';
        $this->formIsActive = true;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $question = LmsFeedbackQuestion::findOrFail($id);

        abort_unless(Auth::user()->can('update', $question), 403);

        $this->editingId = $question->id;
        $this->formGroupLabel = (string) $question->group_label;
        $this->formQuestion = $question->question;
        $this->formIsActive = $question->is_active;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function saveForm(): void
    {
        $ability = $this->editingId ? 'update' : 'create';
        $subject = $this->editingId ? LmsFeedbackQuestion::findOrFail($this->editingId) : LmsFeedbackQuestion::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $validated = $this->validate([
            'formGroupLabel' => 'nullable|string|max:255',
            'formQuestion' => 'required|string',
            'formIsActive' => 'boolean',
        ], [], [
            'formGroupLabel' => 'kelompok',
            'formQuestion' => 'pertanyaan',
        ]);

        $data = [
            'group_label' => $validated['formGroupLabel'] ?: null,
            'question' => $validated['formQuestion'],
            'is_active' => $validated['formIsActive'],
        ];

        if ($this->editingId) {
            LmsFeedbackQuestion::whereKey($this->editingId)->update($data);
        } else {
            LmsFeedbackQuestion::create($data);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Pertanyaan berhasil diubah' : 'Pertanyaan baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function removeQuestion(string $id): void
    {
        $question = LmsFeedbackQuestion::withCount('answers')->findOrFail($id);

        abort_unless(Auth::user()->can('delete', $question), 403);

        if ($question->answers_count > 0) {
            Notification::make()
                ->title('Pertanyaan sudah pernah dijawab, tidak bisa dihapus')
                ->danger()
                ->send();

            return;
        }

        $question->delete();

        Notification::make()->title('Pertanyaan berhasil dihapus')->success()->send();
    }

    public function moveUp(string $id): void
    {
        $this->swapWithNeighbor($id, 'up');
    }

    public function moveDown(string $id): void
    {
        $this->swapWithNeighbor($id, 'down');
    }

    private function swapWithNeighbor(string $id, string $direction): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);

        $ordered = LmsFeedbackQuestion::orderBy('sort_order')->get(['id', 'sort_order']);
        $index = $ordered->search(fn ($q) => $q->id === $id);

        if ($index === false) {
            return;
        }

        $neighborIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! $ordered->has($neighborIndex)) {
            return;
        }

        $current = $ordered[$index];
        $neighbor = $ordered[$neighborIndex];

        LmsFeedbackQuestion::whereKey($current->id)->update(['sort_order' => $neighbor->sort_order]);
        LmsFeedbackQuestion::whereKey($neighbor->id)->update(['sort_order' => $current->sort_order]);
    }

    public function render()
    {
        return view('livewire.lms.lms-feedback-questions-page');
    }
}
