<?php

namespace App\Livewire\Assessment;

use App\Enum\CustomPermissionEnum;
use App\Models\Assessment\AssessmentTemplates;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Daftar Template Penilaian portal — padanan AssessmentTemplateResource admin. */
#[Layout('components.layout', ['title' => 'Template Penilaian'])]
class TemplatePenilaianPage extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getTemplatesProperty()
    {
        return AssessmentTemplates::query()
            ->with(['assessmentType', 'company'])
            ->withCount('templateItems')
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%' . trim($this->search) . '%'))
            ->orderBy('name')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.assessment.template-penilaian-page');
    }
}
