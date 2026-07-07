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

        // Check for certificate URL from user history
        $certificateUrl = null;
        if ($this->userHistories && $this->userHistories->isNotEmpty()) {
            $history = $this->userHistories->firstWhere('user_id', $authId);
            $certificateUrl = $history?->certificate_url ?? null;
        }

        $feedbackUser = $this->feedbacks
            ? $this->feedbacks->firstWhere('user_id', $authId)
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
            "is_passed" => $courseUser ? ($courseUser->is_passed ? true : false) : false,
            "has_feedback" => (bool) $feedbackUser,
            "meetings" => $this->meetings ? MeetingsResource::collection($this->meetings) : null,
        ];

        if ($this->userHistories && $this->userHistories->isNotEmpty()) {
            $result["certificate_url"] = $certificateUrl ? asset(Storage::url($certificateUrl)) : null;
        }

        return $result;
    }
}
