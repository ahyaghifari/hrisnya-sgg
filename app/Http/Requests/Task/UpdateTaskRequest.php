<?php

namespace App\Http\Requests\Task;

use App\Enum\Task\TaskRiskLevel;
use App\Enum\Task\TaskStatus;
use App\Helpers\ApiResponseHelper;
use App\Models\Task\Task;
use App\Rules\Task\HighRiskOrOverdueExplanationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $task = Task::find($this->route('id'));

        if (! $task || $task->status != TaskStatus::IN_PROGRESS->value) {
            return false;
        }

        return $task->assigned_to === $user->id;
    }

    protected function failedAuthorization(): void
    {
        abort(ApiResponseHelper::forbidden());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'completed_date' => 'required|date|after_or_equal:start_date',
            'total_cost' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'risk_level' => [
                'required',
                Rule::in(array_column(TaskRiskLevel::cases(), 'value')),
            ],
            'collaboration_note' => 'nullable|string|max:1000',
            'root_cause' => ['nullable', 'string', 'max:1000', new HighRiskOrOverdueExplanationRule()],
            'solution' => ['nullable', 'string', 'max:1000', new HighRiskOrOverdueExplanationRule()],
            'proofs.*' => 'nullable|file|mimes:png,jpg,jpeg|max:5120',
            'proofs_to_delete' => 'nullable|array',
            'proofs_to_delete.*' => 'exists:task_proofs,id',
        ];
    }
}
