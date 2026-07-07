<?php

namespace App\Http\Resources\Attendance;

use App\Enum\AttendanceReqStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListReqResource extends JsonResource
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
            'type' => $this->type,
            'status' => $this->status,
            'status_label' => AttendanceReqStatus::from($this->status)->getLabel(),
            'title' => $this->leaveDetails?->first()?->leave_type
                ?? $this->permissionDetails?->first()?->permission_type
                ?? $this->overtimeDetails?->first()?->overtime_type,
            'date' => $this->created_at ? $this->created_at->translatedFormat('d M') : null,
        ];
    }
}
