<?php

namespace App\Http\Resources\Attendance;

use App\Enum\AttendanceReqStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailReqResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return match ($this->type) {
            'overtime' => (new ReqOvertimeResource($this))->toArray($request),
            'leave' => (new LeaveReqResource($this))->toArray($request),
            'permission' => (new PermissionReqResource($this))->toArray($request),
            default => [
                'id' => $this->id,
                'type' => $this->type,
                'status' => $this->status,
                'status_label' => AttendanceReqStatus::from($this->status)->getLabel(),
            ],
        };
    }
}
