<?php

namespace App\Repositories\Attendance;

use App\Models\Attendance\AttendanceRequests;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User;
use Illuminate\Support\Collection;

class AttendanceImportRepository
{
    public function findUserByNip(string $nip): ?User
    {
        return User::whereNip($nip)->first();
    }

    public function findShiftAssignmentForDate(string $userId, string $date): ?ShiftScheduleAssignments
    {
        return ShiftScheduleAssignments::with('shift')
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();
    }

    public function findApprovedOvertimeRequestsForDate(string $userId, string $date): Collection
    {
        return AttendanceRequests::where('user_id', $userId)
            ->where('type', 'overtime')
            ->where('status', 'approved')
            ->whereHas('overtimeDetails', fn ($q) => $q->where('date', $date))
            ->with(['overtimeDetails' => fn ($q) => $q->where('date', $date)])
            ->get();
    }

    public function findApprovedLeaveRequestsForDate(string $userId, string $date): Collection
    {
        return AttendanceRequests::where('user_id', $userId)
            ->where('type', 'leave')
            ->where('status', 'approved')
            ->whereHas('leaveDetails', fn ($q) => $q
                ->where(fn ($q2) => $q2
                    // end_date filled: range check
                    ->where(fn ($q3) => $q3
                        ->whereNotNull('end_date')
                        ->where('start_date', '<=', $date)
                        ->where('end_date', '>=', $date)
                    )
                    // end_date null: single-day, match by start_date only
                    ->orWhere(fn ($q3) => $q3
                        ->whereNull('end_date')
                        ->where('start_date', $date)
                    )
                )
            )
            ->get();
    }

    public function findApprovedLeaveRequestsInRange(
        string $fromDate,
        string $toDate,
        ?string $userId = null,
        ?string $companyId = null
    ): Collection {
        $overlaps = fn ($q) => $q->where(fn ($q2) => $q2
            // range leave: start_date <= toDate AND end_date >= fromDate
            ->where(fn ($q3) => $q3
                ->whereNotNull('end_date')
                ->where('start_date', '<=', $toDate)
                ->where('end_date', '>=', $fromDate)
            )
            // single-day leave: start_date within range
            ->orWhere(fn ($q3) => $q3
                ->whereNull('end_date')
                ->whereBetween('start_date', [$fromDate, $toDate])
            )
        );

        $query = AttendanceRequests::where('type', 'leave')
            ->where('status', 'approved')
            ->whereHas('leaveDetails', $overlaps)
            ->with([
                'leaveDetails' => $overlaps,
                'user',
            ]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($companyId) {
            $query->whereHas('user', fn ($q) => $q->whereCompany($companyId));
        }

        return $query->get();
    }

    public function findApprovedPermissionRequestsForDate(string $userId, string $date): Collection
    {
        return AttendanceRequests::where('user_id', $userId)
            ->where('type', 'permission')
            ->where('status', 'approved')
            ->whereHas('permissionDetails', fn ($q) => $q->where('date', $date))
            ->with(['permissionDetails' => fn ($q) => $q->where('date', $date)])
            ->get();
    }
}
