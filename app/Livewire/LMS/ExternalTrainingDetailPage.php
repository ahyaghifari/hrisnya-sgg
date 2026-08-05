<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\TrainingRequests;
use App\Services\LMS\TrainingRequestHonorariumService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Detail Pelatihan Eksternal'])]
class ExternalTrainingDetailPage extends Component
{
    public TrainingRequests $record;

    /** @var array<string, string> variable_id => category_id */
    public array $categorySelections = [];

    public function mount(TrainingRequests $record): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);

        $record->load(['user', 'honorDetails']);
        $this->record = $record;

        foreach ($record->honorDetails as $detail) {
            $this->categorySelections[$detail->variable_id] = $detail->category_id;
        }
    }

    public function getVariablesProperty()
    {
        return app(TrainingRequestHonorariumService::class)->variables();
    }

    public function saveHonor(): void
    {
        $variableIds = $this->variables->pluck('id');

        foreach ($variableIds as $variableId) {
            if (empty($this->categorySelections[$variableId])) {
                $this->addError('categorySelections', 'Semua variabel wajib dipilih salah satu kategorinya.');

                return;
            }
        }

        app(TrainingRequestHonorariumService::class)->saveScoredAssessment(
            $this->record,
            array_intersect_key($this->categorySelections, array_flip($variableIds->all()))
        );

        $this->record->refresh();

        Notification::make()->title('Ikatan dinas berhasil disimpan')->success()->send();
    }

    public function render()
    {
        return view('livewire.lms.external-training-detail-page');
    }
}
