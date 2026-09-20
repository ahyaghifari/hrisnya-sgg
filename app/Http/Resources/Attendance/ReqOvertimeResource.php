<?php

namespace App\Http\Resources\Attendance;

use App\Enum\AttendanceReqStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReqOvertimeResource extends JsonResource
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
            'date' => optional($this->overtimeDetails->first())->date
                ? (function ($d) {
                    $dt = \Carbon\Carbon::parse($d)->locale('id');
                    return $dt->translatedFormat('l') . ', ' . $dt->format('d') . ' ' . mb_strtolower($dt->translatedFormat('F')) . ' ' . $dt->format('Y');
                })(optional($this->overtimeDetails->first())->date)
                : null,
            'type' => $this->type,
            'overtime_type' => optional($this->overtimeDetails->first())->overtime_type,
            'tasks' => $this->overtimeDetails->map(function ($detail) {
                return [
                    'task_description' => $detail->task_description,
                    'start_time' => $detail->start_time,
                    'end_time' => $detail->end_time,
                ];
            }),
            'attachments' => $this->attachments->map(function ($attachment) {
                return [
                    'file_path' => $attachment->file_path,
                    'url' => asset('storage/' . $attachment->file_path),
                ];
            }),
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
                'department' => $this->user->profile?->jobPosition?->orgStructure?->name ?? null,
                'employee_type' => $this->user->profile?->employee_type ?? null,
            ],
        ];
    }
}
