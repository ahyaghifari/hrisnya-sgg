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
            'type_id' => ['required', \Illuminate\Validation\Rule::enum(\App\Enum\Attendance\OvertimeTypeEnum::class)],

            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.task_description' => ['required', 'string', 'max:500'],
            'tasks.*.start_time' => ['required', 'date_format:H:i'],
            'tasks.*.end_time' => [
                'required',
                'date_format:H:i',
                'after:tasks.*.start_time',
            ],

            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // max 10MB per file
        ];
    }
}
