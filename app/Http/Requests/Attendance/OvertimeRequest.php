<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class OvertimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'type_id' => ['required', 'exists:m_overtime_types,id'],

            'tasks' => ['nullable', 'array', 'min:1'],
            'tasks.*.task_description' => ['nullable', 'string', 'max:500'],
            'tasks.*.start_time' => ['nullable', 'date_format:H:i'],
            'tasks.*.end_time' => [
                'nullable',
                'date_format:H:i',
                'after:tasks.*.start_time',
            ],

            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // max 10MB per file
        ];
    }
}
