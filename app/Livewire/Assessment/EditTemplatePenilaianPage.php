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

#[Layout('components.layout', ['title' => 'Edit Template Penilaian'])]
class EditTemplatePenilaianPage extends Component
{
    public AssessmentTemplates $template;

    public string $name = '';
    public ?string $type_id = null;
    public ?string $job_position_id = null;
    public ?string $description = null;
    public bool $is_active = true;

    /** @var array<int, array{id: ?string, name: string, group_label: ?string, weight_percent: ?string, target_score: ?string, max_score: ?string}> */
    public array $items = [];

    /** @var array<int, string> id item yang dihapus dari form, dieksekusi saat submit(). */
    public array $deletedItemIds = [];

    public function mount(AssessmentTemplates $template): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value), 403);

        $this->template = $template;
        $this->name = $template->name;
        $this->type_id = $template->type_id;
        $this->job_position_id = $template->job_position_id;
        $this->description = $template->description;
        $this->is_active = (bool) $template->is_active;

        $this->items = $template->templateItems()->orderBy('sort_order')->get()
            ->map(fn (AssessmentTemplateItems $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'group_label' => $item->group_label,
                'weight_percent' => $item->weight_percent,
                'target_score' => $item->target_score,
                'max_score' => $item->max_score,
            ])
            ->all();

        if ($this->items === []) {
            $this->items = [$this->emptyItem()];
        }
    }

    private function emptyItem(): array
    {
        return ['id' => null, 'name' => '', 'group_label' => null, 'weight_percent' => null, 'target_score' => null, 'max_score' => 5];
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem();
    }

    public function removeItem(int $index): void
    {
        if (! empty($this->items[$index]['id'])) {
            $this->deletedItemIds[] = $this->items[$index]['id'];
        }

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
            'items.*.id' => ['nullable', 'string'],
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
            $this->template->update([
                'type_id' => $data['type_id'],
                'job_position_id' => $data['job_position_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'is_active' => $data['is_active'],
            ]);

            AssessmentTemplateItems::whereIn('id', $this->deletedItemIds)->delete();

            foreach ($data['items'] as $index => $item) {
                AssessmentTemplateItems::updateOrCreate(
                    ['id' => $item['id'] ?? null, 'template_id' => $this->template->id],
                    [
                        'template_id' => $this->template->id,
                        'sort_order' => $index + 1,
                        'name' => $item['name'],
                        'group_label' => $item['group_label'],
                        'weight_percent' => $item['weight_percent'],
                        'target_score' => $item['target_score'],
                        'max_score' => $item['max_score'] ?? 5,
                    ]
                );
            }
        });

        Notification::make()->title('Template penilaian berhasil disimpan')->success()->send();

        $this->redirect(route('penilaian.template'), navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.edit-template-penilaian-page');
    }
}
