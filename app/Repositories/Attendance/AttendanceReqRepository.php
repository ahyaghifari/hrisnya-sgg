<?php

namespace App\Repositories\Attendance;

use App\Models\Attendance\AttendanceRequestHistories;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AttendanceReqRepository
{
    public function getMasterAttendanceReqTypes(string $type, ?string $companyId = null)
    {
        $companyId ??= auth('sanctum')->user()->company_id;

        if ($type === 'leave') {
            return LeaveTypes::where('company_id', $companyId)->get();
        } else if ($type === 'permission') {
            return PermissionTypes::where('company_id', $companyId)->get();
        } else if ($type === 'overtime') {
            // Jenis lembur tetap (enum), bukan master per perusahaan; bentuknya disamakan {id, name} buat RequestTypeResource.
            return collect(\App\Enum\Attendance\OvertimeTypeEnum::options())
                ->map(fn ($label, $key) => (object) ['id' => $key, 'name' => $label])
                ->values();
        } else {
            throw new \InvalidArgumentException("Invalid attendance request type: $type");
        }
    }

    public function getListAttendanceRequestsByUserId(string $userId, array $filters = [])
    {
        $query = AttendanceRequests::with('overtimeDetails', 'leaveDetails', 'permissionDetails')
            ->where('user_id', $userId);

        if (!empty($filters)) {
            // filter by type
            if (array_key_exists('type', $filters) && $filters['type'] !== null) {
                if (is_array($filters['type'])) {
                    $query->whereIn('type', $filters['type']);
                } else {
                    $query->where('type', $filters['type']);
                }
                unset($filters['type']);
            }

            // filter by status
            if (array_key_exists('status', $filters) && $filters['status'] !== null) {
                if (is_array($filters['status'])) {
                    $query->whereIn('status', $filters['status']);
                } else {
                    $query->where('status', $filters['status']);
                }
                unset($filters['status']);
            }

            // filter by periode pengajuan (tanggal dibuat)
            if (array_key_exists('date_from', $filters) && $filters['date_from']) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
                unset($filters['date_from']);
            }

            if (array_key_exists('date_to', $filters) && $filters['date_to']) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
                unset($filters['date_to']);
            }

            // apply any remaining filters as column => value pairs
            if (!empty($filters)) {
                $query->where($filters);
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function findAttendanceRequestById(string $id)
    {
        return AttendanceRequests::with('user.profile.jobPosition.orgStructure','overtimeDetails', 'leaveDetails', 'permissionDetails', 'attachments')->find($id);
    }

    public function storeAttendanceRequest(array $data)
    {
        return AttendanceRequests::create($data);
    }

    public function updateAttendanceRequest(AttendanceRequests $attendanceRequest, array $data)
    {
        $attendanceRequest->update($data);
        return $attendanceRequest;
    }
}
