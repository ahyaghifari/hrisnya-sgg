<?php

namespace App\Livewire\Assessment;

use App\Enum\CustomPermissionEnum;
use App\Models\Assessment\Assessments;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Daftar Jadwal Penilaian portal — padanan AssessmentResource admin. */
#[Layout('components.layout', ['title' => 'Jadwal Penilaian'])]
class JadwalPenilaianPage extends Component
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

    public function getAssessmentsProperty()
    {
        return Assessments::query()
            ->with(['template.assessmentType', 'jobPosition'])
            ->withCount('userAssessments')
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%' . trim($this->search) . '%'))
            ->latest()
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.assessment.jadwal-penilaian-page');
    }
}
