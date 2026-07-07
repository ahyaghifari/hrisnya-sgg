<?php

namespace App\Http\Resources\LMS;

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
        $userResult = $this->results()->where('user_id', auth('sanctum')->id())->first();
        return [
            'id' => $this->id,
            'title' => $this->quiz->title,
            'is_completed' => $userResult ? ($userResult->is_completed ? true : false) : false,
        ];
    }
}
