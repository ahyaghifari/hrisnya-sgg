<?php

namespace App\Repositories\Attendance;

use App\Models\Attendance\AttendanceRequestAttachments;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\PermissionTypes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PermissionReqRepository
{
    public function getPermissionTypeById(string $id)
    {
        return PermissionTypes::find($id);
    }

    public function findAttendanceReqPermissionDetail(string $attendanceReqId)
    {
        return AttendanceRequests::with('user.profile.orgStructure', 'permissionDetails', 'attachments')->find($attendanceReqId);
    }

    public function storePermissionRequest(array $data): array
    {
        DB::beginTransaction();
        try {
            if (!empty($data['end_date'])) {
                $start = Carbon::parse($data['date']);
                $end = Carbon::parse($data['end_date']);
                $totalDays = $start->diffInDays($end) + 1;
            } else {
                $totalDays = 1;
            }

            $detail = AttendanceRequestPermissionDetails::create([
                'request_id' => $data['request_id'],
                'permission_type_id' => $data['permission_type_id'],
                'permission_type' => $data['permission_type'],
                'category' => $data['category'] ?? null,
                'date' => $data['date'],
                'end_date' => $data['end_date'] ?? null,
                'total_days' => $totalDays,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'location' => $data['location'] ?? null,
                'substitute_user_id' => $data['substitute_user_id'] ?? null,
                'substitute_user_name' => $data['substitute_user_name'] ?? null,
                'substitute_task' => $data['substitute_task'] ?? null,
            ]);

            if (!empty($data['attachments_files']) && is_array($data['attachments_files'])) {
                foreach ($data['attachments_files'] as $file) {
                    if ($file) {
                        $path = Storage::disk('public')->putFile("permission/{$data['request_id']}", $file);

                        AttendanceRequestAttachments::create([
                            'request_id' => $data['request_id'],
                            'file_path' => $path,
                        ]);
                    }
                }
            }

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
