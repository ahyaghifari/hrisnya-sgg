<?php

namespace App\Repositories\Attendance;

use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Attendance\LeaveUserBalances;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Attendance\LeaveTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveReqRepository
{
    public function getLeaveTypeById(string $id)
    {
        return LeaveTypes::find($id);
    }

    public function findAttendanceReqLeaveDetail(string $attendanceReqId)
    {
        return AttendanceRequests::with('user.profile.orgStructure','leaveDetails')->find($attendanceReqId);
    }

    public function getLeaveUserBalance(string $userId, string $typeId): array
    {
        $leaveType = $this->getLeaveTypeById($typeId);
        $balanceRecord = LeaveUserBalances::where('user_id', $userId)
            ->where('leave_type_id', $typeId)
            ->first();

        if ($balanceRecord) {
            $quota = $leaveType ? $leaveType->quota_days : 0;
            $used = $balanceRecord->used;
            $isExist = true;
        } else {
            $quota = $leaveType ? $leaveType->quota_days : 0;
            $used = 0;
            $isExist = false;
        }

        // Cuti bersama cuma mengurangi kuota Cuti Tahunan, bukan tipe cuti lain (izin, dll).
        $collectiveLeaveDays = 0;
        if ($leaveType && str_contains(mb_strtolower($leaveType->name), 'tahunan')) {
            $periodStart = $balanceRecord?->period_start ?? now()->startOfYear();
            $periodEnd = $balanceRecord?->period_end ?? now()->endOfYear();

            $collectiveLeaveDays = Holidays::where('is_collective_leave', true)
                ->whereBetween('date', [$periodStart, $periodEnd])
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $leaveType->company_id))
                ->count();
        }

        $available = $quota - $used - $collectiveLeaveDays;
        if ($available < 0) {
            $available = 0;
        }
        return [
            'quota' => $quota,
            'used' => $used,
            'collective_leave_days' => $collectiveLeaveDays,
            'available' => $available,
            'is_exist' => $isExist,
        ];
    }

    public function updateCreateLeaveUserBalance(array $data): array
    {
        DB::beginTransaction();
        try {
            $leaveType = $this->getLeaveTypeById($data['type_id']);

            if (!$leaveType) {
                throw new \Exception('Leave type not found.');
            }

            $userLeaveBalance = LeaveUserBalances::firstOrCreate(
                ['user_id' => $data['user_id'], 'leave_type_id' => $data['type_id']],
                [
                    'period_start' => now()->startOfYear(),
                    'period_end' => now()->endOfYear(),
                    'quota' => $leaveType->quota_days,
                    'used' => 0,
                ]
            );
            
            $userLeaveBalance->used += $data['total_days'];
            $userLeaveBalance->save();

            DB::commit();

            return [$userLeaveBalance];

        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function storeLeaveRequest(array $data): array
    {
        DB::beginTransaction();
        try {
            if (!empty($data['start_date']) && empty($data['end_date'])) {
                $data['total_days'] = 1;
            } elseif (!empty($data['start_date']) && !empty($data['end_date'])) {
                $start = \Carbon\Carbon::parse($data['start_date']);
                $end = \Carbon\Carbon::parse($data['end_date']);

                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }

                $data['total_days'] = $start->diffInDays($end) + 1;
            } else {
                $data['total_days'] = 0;
            }

            // Update User Balance
            $this->updateCreateLeaveUserBalance([
                'user_id' => $data['user_id'],
                'type_id' => $data['type_id'],
                'total_days' => $data['total_days'],
            ]);

            $detail = AttendanceRequestLeaveDetails::create([
                'request_id' => $data['request_id'],
                'leave_type_id' => $data['type_id'],
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $data['total_days'],
            ]);

            DB::commit();

            return [$detail];

        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
