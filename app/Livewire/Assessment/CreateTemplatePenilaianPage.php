<?php

namespace App\Livewire\Assessment;

use App\Enum\CustomPermissionEnum;
use App\Models\Assessment\AssessmentTemplateItems;
use App\Models\Assessment\AssessmentTemplates;
use App\Models\Assessment\AssessmentTypes;
use App\Models\Master\JobPositions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Tambah Template Penilaian" — padanan CreateAssessmentTemplate admin, versi portal. Cuma
 * indikator generik (name/group_label/weight_percent/target_score/max_score), TIDAK ada
 * metadata MCQ/Orientasi (App\Enum\AssessmentTemplateItemType) — sama seperti keputusan di
 * AssessmentUserForm, topik terpisah dari fase ini.
 */
#[Layout('components.layout', ['title' => 'Tambah Template Penilaian'])]
class CreateTemplatePenilaianPage extends Component
{
    public string $name = '';
    public ?string $type_id = null;
    public ?string $job_position_id = null;
    public ?string $description = null;
    public bool $is_active = true;

    /** @var array<int, array{name: string, group_label: ?string, weight_percent: ?string, target_score: ?string, max_score: ?string}> */
    public array $items = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value), 403);

        $this->items = [$this->emptyItem()];
    }

    private function emptyItem(): array
    {
        return ['name' => '', 'group_label' => null, 'weight_percent' => null, 'target_score' => null, 'max_score' => 5];
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getAssessmentTypesProperty()
    {
        return AssessmentTypes::orderBy('name')->pluck('name', 'id');
    }

    public function getJobPositionsProperty()
    {
        return JobPositions::where('company_id', Auth::user()->company_id)->orderBy('name')->pluck('name', 'id');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type_id' => ['required', 'exists:assessment_types,id'],
            'job_position_id' => ['required', 'exists:m_job_positions,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.group_label' => ['nullable', 'string', 'max:255'],
            'items.*.weight_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.target_score' => ['nullable', 'numeric', 'min:0'],
            'items.*.max_score' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $template = AssessmentTemplates::create([
                'type_id' => $data['type_id'],
                'job_position_id' => $data['job_position_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'is_active' => $data['is_active'],
            ]);

            foreach ($data['items'] as $index => $item) {
                AssessmentTemplateItems::create([
                    'template_id' => $template->id,
                    'sort_order' => $index + 1,
                    'name' => $item['name'],
                    'group_label' => $item['group_label'],
                    'weight_percent' => $item['weight_percent'],
                    'target_score' => $item['target_score'],
                    'max_score' => $item['max_score'] ?? 5,
                ]);
            }
        });

        Notification::make()->title('Template penilaian berhasil dibuat')->success()->send();

        $this->redirect(route('penilaian.template'), navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.create-template-penilaian-page');
    }
}
