<?php

namespace App\Http\Resources\LMS;

use App\Models\LMS\LmsRespondent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingQuizResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $respondentId = LmsRespondent::where('course_id', $this->meeting->course_id)
            ->where('user_id', auth('sanctum')->id())
            ->where('category', LmsRespondent::CATEGORY_KUIS)
            ->value('id');

        $userResult = $respondentId ? $this->results()->where('respondent_id', $respondentId)->first() : null;

        return [
            'id' => $this->id,
            'title' => $this->quiz->title,
            'is_completed' => $userResult ? ($userResult->is_completed ? true : false) : false,
        ];
    }
}
