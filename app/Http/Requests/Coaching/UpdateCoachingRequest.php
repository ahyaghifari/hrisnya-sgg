<?php

namespace App\Http\Requests\Coaching;

use App\Enum\CoachingStatus;
use App\Models\Coaching\Coaching;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $coaching = Coaching::find($this->route('id'));

        $allowedStatuses = [
            CoachingStatus::IN_PROGRESS->value,
            CoachingStatus::REJECTED->value,
        ];

        if (! $coaching && ! in_array($coaching->status, $allowedStatuses, true)) {
            return false;
        }

        return $coaching->user_id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proofs.*' => 'required|file|mimes:png,jpg,jpeg|max:5120',
        ];
    }
}
