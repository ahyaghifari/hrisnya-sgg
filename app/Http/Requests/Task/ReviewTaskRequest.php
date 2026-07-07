<?php

namespace App\Http\Requests\Task;

use App\Enum\Task\TaskStatus;
use App\Helpers\ApiResponseHelper;
use App\Models\Task\Task;
use Illuminate\Foundation\Http\FormRequest;

class ReviewTaskRequest extends FormRequest
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

        return $task->sla_reviewer_id === $user->id;
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
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ];
    }
}
