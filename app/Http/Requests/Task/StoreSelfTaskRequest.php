<?php

namespace App\Http\Requests\Task;

use App\Rules\Task\SameDepartmentAndCompanyRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSelfTaskRequest extends FormRequest
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
            'requirement' => 'required|string|max:1000',
            'due_date' => 'required|date|after_or_equal:today',
            'total_cost' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'sla_reviewer_id' => [
                'required',
                'exists:users,id',
                new SameDepartmentAndCompanyRule(),
            ],
        ];
    }
}
