<?php

namespace App\Livewire;

use App\Models\Attendance\AttendanceRequests;
use App\Services\Approval\ApprovalTimelineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyAttendanceStatusCard extends Component
{
    public function render()
    {
        $request = AttendanceRequests::where('user_id', Auth::id())
            ->whereIn('status', ['submitted', 'in_review'])
            ->with(['leaveDetails', 'permissionDetails', 'overtimeDetails'])
            ->latest()
            ->first();

        $timeline = $request ? app(ApprovalTimelineService::class)->build($request) : [];
        $summary = $request ? $this->buildSummary($request) : null;

        return view('livewire.my-attendance-status-card', [
            'request' => $request,
            'timeline' => $timeline,
            'summary' => $summary,
        ]);
    }

    /**
     * @return array{type_name: string, date_label: string}
     */
    private function buildSummary(AttendanceRequests $request): array
    {
        return match ($request->type) {
            'leave' => $this->leaveSummary($request),
            'permission' => $this->permissionSummary($request),
            'overtime' => $this->overtimeSummary($request),
            default => ['type_name' => $request->type, 'date_label' => '-'],
        };
    }

    private function leaveSummary(AttendanceRequests $request): array
    {
        $detail = $request->leaveDetails->first();

        if (! $detail) {
            return ['type_name' => 'Cuti', 'date_label' => '-'];
        }

        $start = Carbon::parse($detail->start_date);
        $end = $detail->end_date ? Carbon::parse($detail->end_date) : $start;

        $dateLabel = $start->isSameDay($end)
            ? $start->translatedFormat('d M Y')
            : $start->translatedFormat('d M') . ' - ' . $end->translatedFormat('d M Y');

        return ['type_name' => $detail->leave_type ?? 'Cuti', 'date_label' => $dateLabel];
    }

    private function permissionSummary(AttendanceRequests $request): array
    {
        $detail = $request->permissionDetails->first();

        return [
            'type_name' => $detail?->permission_type ?? 'Izin',
            'date_label' => $detail?->date ? Carbon::parse($detail->date)->translatedFormat('d M Y') : '-',
        ];
    }

    private function overtimeSummary(AttendanceRequests $request): array
    {
        $detail = $request->overtimeDetails->first();

        return [
            'type_name' => $detail?->overtime_type ?? 'Lembur',
            'date_label' => $detail?->date ? Carbon::parse($detail->date)->translatedFormat('d M Y') : '-',
        ];
    }
}
