<?php

namespace App\Livewire\Assessment;

use App\Models\Assessment\AssessmentTemplates;
use App\Models\Assessment\Assessments;
use App\Models\Master\JobPositions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** "Tambah Jadwal Penilaian" — padanan CreateAssessment admin, versi portal. */
#[Layout('components.layout', ['title' => 'Tambah Jadwal Penilaian'])]
class CreateJadwalPenilaianPage extends Component
{
    public string $name = '';
    public ?string $template_id = null;
    public ?string $job_position_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public string $status = 'active';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('create', Assessments::class), 403);
    }

    public function getTemplatesProperty()
    {
        return AssessmentTemplates::where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function getJobPositionsProperty()
    {
        return JobPositions::where('company_id', Auth::user()->company_id)->orderBy('name')->pluck('name', 'id');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'exists:assessment_templates,id'],
            'job_position_id' => ['nullable', 'exists:m_job_positions,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        Assessments::create([
            'company_id' => Auth::user()->company_id,
            'template_id' => $data['template_id'],
            'job_position_id' => $data['job_position_id'],
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
        ]);

        Notification::make()->title('Jadwal penilaian berhasil dibuat')->success()->send();

        $this->redirect(route('penilaian.jadwal'), navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.create-jadwal-penilaian-page');
    }
}
