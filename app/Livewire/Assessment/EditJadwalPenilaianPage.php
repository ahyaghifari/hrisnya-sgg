<?php

namespace App\Livewire\Assessment;

use App\Enum\CustomPermissionEnum;
use App\Models\Assessment\AssessmentTemplates;
use App\Models\Assessment\Assessments;
use App\Models\Master\JobPositions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Edit Jadwal Penilaian'])]
class EditJadwalPenilaianPage extends Component
{
    public Assessments $assessment;

    public string $name = '';
    public ?string $template_id = null;
    public ?string $job_position_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public string $status = 'active';

    public function mount(Assessments $assessment): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value), 403);

        $this->assessment = $assessment;
        $this->name = $assessment->name;
        $this->template_id = $assessment->template_id;
        $this->job_position_id = $assessment->job_position_id;
        $this->start_date = $assessment->start_date;
        $this->end_date = $assessment->end_date;
        $this->status = $assessment->status;
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

        $this->assessment->update($data);

        Notification::make()->title('Jadwal penilaian berhasil disimpan')->success()->send();

        $this->redirect(route('penilaian.jadwal'), navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.edit-jadwal-penilaian-page');
    }
}
