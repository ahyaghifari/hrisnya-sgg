<?php

namespace App\Http\Resources\LMS;

use App\Enum\AttendanceStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meetingAttendance = $this->attendance ? $this->attendance->firstWhere('user_id', auth('sanctum')->id()) : null;
        
        $now = Carbon::now();
        $startDateTime = Carbon::parse($this->start_date . ' ' . $this->start_time);
        $endDateTime = Carbon::parse($this->end_date . ' ' . $this->end_time);
        $is_active = $now->between($startDateTime, $endDateTime);
        $meetingUser = $this->meetingUsers ? $this->meetingUsers->firstWhere('user_id', auth('sanctum')->id()) : null;

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'meeting_time' => $this->start_date && $this->start_time ? 
                Carbon::parse($this->start_date)->format('d M Y') . ' ' . Carbon::parse($this->start_time)->format('H:i') . ' - ' . Carbon::parse($this->end_time)->format('H:i') : null,
            'description' => $this->description,

            'attendance_type' => $this->attendance_type,
            'is_active' => $is_active,
            'allow_attendance' => $meetingUser ? ($meetingAttendance ? false : true) : false,
            'is_present' => $meetingAttendance ? ($meetingAttendance->status === AttendanceStatus::PRESENT->value ? true : false) : null,
            'attendance' => $this->attendance ? new MeetingAttendanceResource($this->attendance) : null,

            'materials' => $this->materials ? MeetingMaterialsResource::collection($this->materials) : null,
            'quiz' => $this->quizzes ? MeetingQuizResource::Collection($this->quizzes) : null
        ]; 
    }
}
