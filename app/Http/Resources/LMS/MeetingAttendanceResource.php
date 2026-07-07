<?php

namespace App\Http\Resources\LMS;

use App\Enum\AttendanceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => AttendanceStatus::from($this->status)->name,
            'clocked_in_at' => $this->checked_in_at
        ];
    }
}
