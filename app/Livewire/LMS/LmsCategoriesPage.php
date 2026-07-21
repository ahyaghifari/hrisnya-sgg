<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Enum\LMS\TrainingCategoryEnum;
use App\Models\LMS\LmsCategories;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Padanan portal dari Filament LmsCategoriesResource (/admin/lms-categories) — CRUD kategori
 * pelatihan lewat modal, dipindah ke portal karyawan sesuai arah "migrasi Filament ke portal".
 */
#[Layout('components.layout', ['title' => 'Kategori Pelatihan'])]
class LmsCategoriesPage extends Component
{
    public string $search = '';

    public bool $showFormModal = false;
    public ?string $editingId = null;
    public string $formName = '';
    public ?string $formTrainingCategory = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
    }

    public function getCategoriesProperty()
    {
        $search = trim($this->search);

        return LmsCategories::query()
            ->withCount('courses')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();
    }

    /** @return TrainingCategoryEnum[] */
    public function getTrainingCategoryOptionsProperty(): array
    {
        return TrainingCategoryEnum::internalCases();
    }

    public function openCreateModal(): void
    {
        $this->editingId = null;
        $this->formName = '';
        $this->formTrainingCategory = null;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $category = LmsCategories::findOrFail($id);

        $this->editingId = $category->id;
        $this->formName = $category->name;
        $this->formTrainingCategory = $category->training_category?->value;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function saveForm(): void
    {
        $validated = $this->validate([
            'formName' => 'required|string|max:255',
            'formTrainingCategory' => 'nullable|string|in:'.implode(',', array_column(TrainingCategoryEnum::internalCases(), 'value')),
        ], [], [
            'formName' => 'nama',
            'formTrainingCategory' => 'jenis pelatihan',
        ]);

        $data = [
            'name' => $validated['formName'],
            'training_category' => $validated['formTrainingCategory'] ?: null,
        ];

        if ($this->editingId) {
            LmsCategories::whereKey($this->editingId)->update($data);
        } else {
            LmsCategories::create($data);
        }

        $this->showFormModal = false;

        Notification::make()
            ->title($this->editingId ? 'Kategori berhasil diubah' : 'Kategori baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function removeCategory(string $id): void
    {
        $category = LmsCategories::withCount('courses')->findOrFail($id);

        if ($category->courses_count > 0) {
            Notification::make()
                ->title('Kategori masih dipakai oleh pelatihan, tidak bisa dihapus')
                ->danger()
                ->send();

            return;
        }

        $category->delete();

        Notification::make()->title('Kategori berhasil dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.lms.lms-categories-page');
    }
}
