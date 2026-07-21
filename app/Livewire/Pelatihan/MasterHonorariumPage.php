<?php

namespace App\Livewire\Pelatihan;

use App\Enum\CustomPermissionEnum;
use App\Enum\LMS\TrainingCategoryEnum;
use App\Models\LMS\Trainer\LmsHonorarium;
use App\Models\LMS\Trainer\LmsHonorariumCategory;
use App\Models\LMS\Trainer\LmsHonorariumVariable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Master Honorarium — variabel/kategori/range skor buat 2 konteks yang honornya DIHITUNG
 * (bukan fixed): Dokter Spesialis & Biaya Pelatihan Eksternal. Lihat
 * knowledge/markdown/analisis-honorarium-trainer-pelatihan.md untuk konsep bisnisnya.
 * Global, tidak per company (dikonfirmasi user). Variabel & Kategori sengaja dipisah jadi
 * 2 aksi/modal independen (bukan 1 form gabungan) atas permintaan user.
 */
#[Layout('components.layout', ['title' => 'Master Honorarium'])]
class MasterHonorariumPage extends Component
{
    public string $context = '';

    public bool $showVariableModal = false;

    public bool $showCategoryModal = false;

    public bool $showRangeModal = false;

    public bool $rangeIsSingle = true;

    public array $newVariable = [
        'name' => '',
        'description' => '',
    ];

    public array $newCategory = [
        'variable_id' => '',
        'name' => '',
        'score' => '',
    ];

    public array $newRange = [
        'min_score' => '',
        'max_score' => '',
        'honor_amount' => '',
    ];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);

        $this->context = TrainingCategoryEnum::scoredCases()[0]->value;
    }

    public function setContext(string $context): void
    {
        if (TrainingCategoryEnum::tryFrom($context) === null) {
            return;
        }

        $this->context = $context;
        $this->resetVariableForm();
        $this->resetCategoryForm();
        $this->resetRangeForm();
    }

    public function openVariableModal(): void
    {
        $this->resetVariableForm();
        $this->showVariableModal = true;
    }

    public function closeVariableModal(): void
    {
        $this->showVariableModal = false;
        $this->resetVariableForm();
    }

    public function openCategoryModal(?string $variableId = null): void
    {
        $this->resetCategoryForm();

        if ($variableId) {
            $this->newCategory['variable_id'] = $variableId;
        }

        $this->showCategoryModal = true;
    }

    public function closeCategoryModal(): void
    {
        $this->showCategoryModal = false;
        $this->resetCategoryForm();
    }

    public function openRangeModal(): void
    {
        $this->resetRangeForm();
        $this->showRangeModal = true;
    }

    public function closeRangeModal(): void
    {
        $this->showRangeModal = false;
        $this->resetRangeForm();
    }

    public function saveVariable(): void
    {
        $validated = $this->validate([
            'newVariable.name' => 'required|string|max:255',
            'newVariable.description' => 'nullable|string',
        ]);

        LmsHonorariumVariable::create([
            'context' => $this->context,
            'name' => $validated['newVariable']['name'],
            'description' => $validated['newVariable']['description'] ?? null,
        ]);

        $this->closeVariableModal();

        Notification::make()->title('Variabel berhasil ditambahkan')->success()->send();
    }

    public function deleteVariable(string $id): void
    {
        LmsHonorariumVariable::where('context', $this->context)->findOrFail($id)->delete();

        Notification::make()->title('Variabel dihapus')->success()->send();
    }

    public function saveCategory(): void
    {
        $validated = $this->validate([
            'newCategory.variable_id' => 'required|exists:lms_honorarium_variables,id',
            'newCategory.name' => 'required|string|max:255',
            'newCategory.score' => 'required|integer',
        ]);

        LmsHonorariumVariable::where('context', $this->context)->findOrFail($validated['newCategory']['variable_id']);

        LmsHonorariumCategory::create($validated['newCategory']);

        $this->closeCategoryModal();

        Notification::make()->title('Kategori berhasil ditambahkan')->success()->send();
    }

    public function deleteCategory(string $id): void
    {
        LmsHonorariumCategory::whereHas('variable', fn ($q) => $q->where('context', $this->context))
            ->findOrFail($id)
            ->delete();

        Notification::make()->title('Kategori dihapus')->success()->send();
    }

    public function saveRange(): void
    {
        if ($this->rangeIsSingle) {
            $this->newRange['max_score'] = $this->newRange['min_score'];
        }

        $validated = $this->validate([
            'newRange.min_score' => 'required|integer',
            'newRange.max_score' => 'required|integer|gte:newRange.min_score',
            'newRange.honor_amount' => 'required|numeric|min:0',
        ]);

        LmsHonorarium::create([
            'context' => $this->context,
            'min_score' => $validated['newRange']['min_score'],
            'max_score' => $validated['newRange']['max_score'],
            'honor_amount' => $validated['newRange']['honor_amount'],
        ]);

        $this->closeRangeModal();

        Notification::make()->title('Range honorarium berhasil ditambahkan')->success()->send();
    }

    public function deleteRange(string $id): void
    {
        LmsHonorarium::where('context', $this->context)->findOrFail($id)->delete();

        Notification::make()->title('Range honorarium dihapus')->success()->send();
    }

    private function resetVariableForm(): void
    {
        $this->newVariable = ['name' => '', 'description' => ''];
        $this->resetErrorBag();
    }

    private function resetCategoryForm(): void
    {
        $this->newCategory = ['variable_id' => '', 'name' => '', 'score' => ''];
        $this->resetErrorBag();
    }

    private function resetRangeForm(): void
    {
        $this->newRange = ['min_score' => '', 'max_score' => '', 'honor_amount' => ''];
        $this->rangeIsSingle = true;
        $this->resetErrorBag();
    }

    /** @return TrainingCategoryEnum[] */
    public function getScoredContextsProperty(): array
    {
        return TrainingCategoryEnum::scoredCases();
    }

    public function getVariablesProperty()
    {
        return LmsHonorariumVariable::where('context', $this->context)
            ->with('categories')
            ->orderBy('name')
            ->get();
    }

    public function getRangesProperty()
    {
        return LmsHonorarium::where('context', $this->context)
            ->orderBy('min_score')
            ->get();
    }

    public function render()
    {
        return view('livewire.pelatihan.master-honorarium-page');
    }
}
