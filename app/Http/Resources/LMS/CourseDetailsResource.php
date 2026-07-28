<?php

namespace App\Http\Resources\LMS;

use App\Enum\CourseUserStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CourseDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authId = auth('sanctum')->id();

        $courseUser = $this->courseUsers
            ? $this->courseUsers->firstWhere('user_id', $authId)
            : null;

        $statusEnum = $courseUser?->status 
            ? CourseUserStatus::from($courseUser->status) 
            : null;

        $feedbackUser = $this->feedbacks
            ? $this->feedbacks->first(fn ($feedback) => $feedback->respondent?->user_id === $authId)
            : null;

        $result = [
            "id"=> $this->id,
            "category_id"=> $this->category_id,
            "category" => $this->category ? $this->category->name : null,
            "title"=> $this->code . ' - ' . $this->title,
            "period_time" => $this->start_date ? Carbon::parse($this->start_date)->format('d M Y') . ' - ' . ($this->end_date ? Carbon::parse($this->end_date)->format('d M Y') : '-') : '-',
            "thumbnail" => $this->thumbnail ? asset(Storage::url($this->thumbnail)) : null,
            "total_meetings" => $this->meetings ? $this->meetings->count() : 0,
            "score" => $courseUser->score ?? 0,
            "status" => $statusEnum?->getLabel(),
            "is_followed" => $courseUser ? $statusEnum?->value !== CourseUserStatus::REQUESTED->value : false,
            "is_mandatory" => (bool) ($courseUser->is_mandatory ?? false),
            "is_passed" => $courseUser ? ($courseUser->is_passed ? true : false) : false,
            "due_date" => $this->due_date ? Carbon::parse($this->due_date)->format('d M Y') : null,
            "is_overdue" => $this->isOverdue() && $statusEnum?->value !== CourseUserStatus::COMPLETED->value,
            "has_feedback" => (bool) $feedbackUser,
            "meetings" => $this->meetings ? MeetingsResource::collection($this->meetings) : null,
        ];

        if ($courseUser) {
            $result["certificate_url"] = $courseUser->certificate_url ? asset(Storage::url($courseUser->certificate_url)) : null;
        }

        return $result;
    }
}
