<?php

namespace App\Http\Requests\Task;

use App\Rules\Task\DepartmentLevelRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => 'required|exists:m_task_services,id',
            'department_id' => ['required', 'exists:m_org_structures,id', new DepartmentLevelRule()],
            'requirement' => 'required|string',
            'attachments.*' => 'nullable|file|mimes:png,jpg,jpeg|max:5120',
        ];
    }
}
