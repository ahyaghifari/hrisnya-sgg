<?php

namespace App\Http\Resources\Attendance;

use App\Enum\AttendanceReqStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionReqResource extends JsonResource
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
            'status' => $this->status,
            'status_label' => AttendanceReqStatus::from($this->status)->getLabel(),
            'type' => $this->type,
            'permission_type' => $this->permissionDetails->first()->permission_type ?? null,
            'purpose' => $this->permissionDetails->first()->purpose ?? null,
            'location' => $this->permissionDetails->first()->location ?? null,
            'substitute_user_name' => $this->permissionDetails->first()->substitute_user_name ?? null,
            'substitute_task' => $this->permissionDetails->first()->substitute_task ?? null,
            'permission_duration' => (function () {
                $detail = $this->permissionDetails->first();
                $date = $detail->date ?? $this->date ?? null;
                $startTime = $detail->start_time ?? $this->start_time ?? null;
                $endTime = $detail->end_time ?? $this->end_time ?? null;
                if (empty($date) || empty($startTime) || empty($endTime)) {
                    return null;
                }
                $start = \Carbon\Carbon::parse("$date $startTime")->locale('id');
                $end = \Carbon\Carbon::parse("$date $endTime")->locale('id');
                // Example: "Senin, 12 Oktober 2025 pukul 10.00 - 12.00"
                $datePart = $start->isoFormat('dddd, D MMMM YYYY');
                $startTimePart = $start->format('H.i');
                $endTimePart = $end->format('H.i');
                return $datePart . ' pukul ' . $startTimePart . ' - ' . $endTimePart;
            })(),
            'note_approvals' => $this->approvalHistories
                ?->whereIn('action', ['approved', 'rejected'])
                ?->sortByDesc('created_at')
                ?->first()
                ?->note ?? null,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'nip' => $this->user->nip,
                'email' => $this->user->email,
                'department' => $this->user->profile?->department?->name ?? null,
                'employee_type' => $this->user->profile?->employee_type ?? null,
                'address' => $this->user->profile?->address ?? null,
                'phone' => $this->user->profile?->phone ?? null,
            ],
        ];
    }
}
