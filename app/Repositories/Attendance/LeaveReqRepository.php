<?php

namespace App\Repositories\Attendance;

use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Attendance\LeaveUserBalances;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Attendance\LeaveTypes;
use Illuminate\Support\Facades\DB;

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

    /**
     * "used" dihitung LIVE dari pengajuan yang sudah DISETUJUI (status=approved) dalam periode
     * ini — bukan baca kolom LeaveUserBalances::used (dulu ditambah manual saat submit, tidak
     * pernah dikurangi lagi saat reject/cancel, jadi gampang basi/bug). Approve/reject/cancel
     * otomatis konsisten karena tidak ada apa pun yang perlu "dikembalikan". Lihat
     * planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §6.
     */
    public function getLeaveUserBalance(string $userId, string $typeId): array
    {
        $leaveType = $this->getLeaveTypeById($typeId);
        $balanceRecord = LeaveUserBalances::where('user_id', $userId)
            ->where('leave_type_id', $typeId)
            ->first();

        $quota = $leaveType ? $leaveType->quota_days : 0;
        $isExist = (bool) $balanceRecord;
        $periodStart = $balanceRecord?->period_start ?? now()->startOfYear();
        $periodEnd = $balanceRecord?->period_end ?? now()->endOfYear();

        $used = (int) AttendanceRequestLeaveDetails::query()
            ->where('leave_type_id', $typeId)
            ->whereBetween('start_date', [$periodStart, $periodEnd])
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('user_id', $userId)
                ->where('type', 'leave')
                ->where('status', 'approved'))
            ->sum('total_days');

        // Cuti bersama cuma mengurangi kuota Cuti Tahunan, bukan tipe cuti lain (izin, dll).
        $collectiveLeaveDays = 0;
        if ($leaveType && str_contains(mb_strtolower($leaveType->name), 'tahunan')) {
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

            // Saldo TIDAK lagi dipotong di sini saat submit — dihitung live dari request
            // approved lewat getLeaveUserBalance(), lihat komentar di method itu.
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
