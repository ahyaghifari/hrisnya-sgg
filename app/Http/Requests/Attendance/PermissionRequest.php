<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
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
            'type_id' => ['required', \Illuminate\Validation\Rule::enum(\App\Enum\Attendance\PermissionTypeEnum::class)],
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'nullable|string',
            'location' => 'nullable|string',
            'substitute_user_id' => 'nullable|exists:users,id',
            'substitute_user_name' => 'nullable|string',
            'substitute_task' => 'nullable|string',
        ];
    }
}
