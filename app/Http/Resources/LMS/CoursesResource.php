<?php

namespace App\Http\Resources\LMS;

use App\Enum\CourseUserStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CoursesResource extends JsonResource
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

        return [
            "id" => $this->id,
            "category_id" => $this->category_id,
            "category" => $this->category?->name,
            "title" => $this->code . ' - ' . $this->title,
            "period_time" => $this->start_date
                ? Carbon::parse($this->start_date)->format('d M Y') . ' - ' . ($this->end_date ? Carbon::parse($this->end_date)->format('d M Y') : '-')
                : '-',

            "status" => $statusEnum?->getLabel(),

            "total_members" => $this->courseUsers?->count() ?? 0,

            "is_followed" => $courseUser ? $statusEnum?->value !== CourseUserStatus::REQUESTED->value : false,

            "is_mandatory" => (bool) ($courseUser->is_mandatory ?? false),

            "is_overdue" => $this->isOverdue() && $statusEnum?->value !== CourseUserStatus::COMPLETED->value,

            "type_nakes" => $this->type_nakes,
            "type" => $this->type,

            "thumbnail" => $this->thumbnail ? asset(Storage::url($this->thumbnail)) : null,

            "member_profile_picture" => $this->courseUsers->take(3)->map(function ($courseUser) {
                $user = $courseUser->user;
                $profile = $user?->profile;
                $profilePicture = $profile?->profile_picture;
                
                if ($profilePicture) {
                    return ["url" => asset(Storage::url($profilePicture))];
                } else {
                    $name = urlencode($user?->name ?? 'User');
                    return ["url" => "https://ui-avatars.com/api/?name={$name}&color=7F9CF5&background=EBF4FF"];
                }
            })->toArray()
        ];
    }
}
