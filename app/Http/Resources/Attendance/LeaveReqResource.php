<?php

namespace App\Http\Resources\Attendance;

use App\Enum\AttendanceReqStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveReqResource extends JsonResource
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
            'leave_type' => $this->leaveDetails->first()->leave_type ?? null,
            'leave_duration' => (function () {
                $detail = $this->leaveDetails->first();
                $startDate = $detail->start_date ?? $this->start_date ?? null;
                $endDate = $detail->end_date ?? $this->end_date ?? null;

                if (empty($startDate)) {
                    return null;
                }

                $start = \Carbon\Carbon::parse($startDate)->locale('id');
                if (empty($endDate) || $endDate == $startDate) {
                    return $start->isoFormat('D MMMM YYYY');
                }

                $end = \Carbon\Carbon::parse($endDate)->locale('id');
                if ($start->format('Ym') === $end->format('Ym')) {
                    return $start->isoFormat('D') . '-' . $end->isoFormat('D MMMM YYYY');
                }
                if ($start->format('Y') === $end->format('Y')) {
                    return $start->isoFormat('D MMMM') . ' - ' . $end->isoFormat('D MMMM YYYY');
                }
                return $start->isoFormat('D MMMM YYYY') . ' - ' . $end->isoFormat('D MMMM YYYY');
            })(),
            'remaining_leave_quota' => (function () {
                $leaveDetail = $this->leaveDetails->first();
                if (!$leaveDetail || !$leaveDetail->leave_type_id) {
                    return null;
                }

                try {
                    $leaveReqService = app(\App\Services\Attendance\AttendaceReqService::class);
                    $balance = $leaveReqService->getLeaveUserBalance($this->user->id, $leaveDetail->leave_type_id, $leaveDetail->start_date);
                    return $balance['available'] ?? 0;
                } catch (\Exception $e) {
                    return null;
                }
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
