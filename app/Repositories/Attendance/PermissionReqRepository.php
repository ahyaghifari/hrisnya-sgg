<?php

namespace App\Repositories\Attendance;

use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\PermissionTypes;
use Illuminate\Support\Facades\DB;

class PermissionReqRepository
{
    public function getPermissionTypeById(string $id)
    {
        return PermissionTypes::find($id);
    }

    public function findAttendanceReqPermissionDetail(string $attendanceReqId)
    {
        return AttendanceRequests::with('user.profile.orgStructure','permissionDetails')->find($attendanceReqId);
    }

    public function storePermissionRequest(array $data): array
    {
        DB::beginTransaction();
        try {
            $detail = AttendanceRequestPermissionDetails::create([
                'request_id' => $data['request_id'],
                'permission_type_id' => $data['permission_type_id'],
                'permission_type' => $data['permission_type'],
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'purpose' => $data['purpose'] ?? null,
                'location' => $data['location'] ?? null,
                'substitute_user_id' => $data['substitute_user_id'] ?? null,
                'substitute_user_name' => $data['substitute_user_name'] ?? null,
                'substitute_task' => $data['substitute_task'] ?? null,
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
