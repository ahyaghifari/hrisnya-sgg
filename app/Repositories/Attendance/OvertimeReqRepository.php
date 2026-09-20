<?php

namespace App\Repositories\Attendance;

use App\Models\Attendance\AttendanceRequestOvertimeDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Attendance\AttendanceRequestAttachments;
use App\Models\Master\Attendance\OvertimeTypes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OvertimeReqRepository
{
    public function getOvertimeTypeById(string $id)
    {
        return OvertimeTypes::find($id);
    }

    public function findAttendanceReqOvertimeDetail(string $attendanceReqId)
    {
        return AttendanceRequests::with('user.profile.jobPosition.orgStructure','overtimeDetails', 'attachments')->find($attendanceReqId);
    }
    
    public function storeOvertimeRequest(array $data): array
    {
        DB::beginTransaction();
        try {
            // Handle file uploads - store to AttendanceRequestAttachments model
            if (!empty($data['attachments_files']) && is_array($data['attachments_files'])) {
                foreach ($data['attachments_files'] as $file) {
                    if ($file) {
                        $path = Storage::disk('public')->putFile("overtime/{$data['request_id']}", $file);
                        
                        // Create attachment record
                        AttendanceRequestAttachments::create([
                            'request_id' => $data['request_id'],
                            'file_path' => $path,
                        ]);
                    }
                }
            }
    
            $created = [];
    
            if (!empty($data['tasks']) && is_array($data['tasks'])) {
                foreach ($data['tasks'] as $task) {
                    $start = $task['start_time'] ?? $data['start_time'] ?? null;
                    $end = $task['end_time'] ?? $data['end_time'] ?? null;
                    $desc = $task['task_description'] ?? $data['task_description'] ?? null;
    
                    $totalHours = null;
                    if ($start && $end) {
                        try {
                            $startC = Carbon::createFromFormat('H:i', $start);
                            $endC = Carbon::createFromFormat('H:i', $end);
                            $minutes = $startC->diffInMinutes($endC);
                            $totalHours = round($minutes / 60, 2);
                        } catch (\Exception $e) {
                            $totalHours = null;
                        }
                    }
    
                    $detail = AttendanceRequestOvertimeDetails::create([
                        'request_id' => $data['request_id'],
                        'overtime_type_id' => $data['overtime_type_id'] ?? $data['overtime_type_id'] ?? null,
                        'overtime_type' => $data['overtime_type'] ?? null,
                        'task_description' => $desc,
                        'date' => $data['date'] ?? null,
                        'start_time' => $start,
                        'end_time' => $end,
                        'total_hours' => $totalHours,
                    ]);
    
                    $created[] = $detail;
                }

                DB::commit();
    
                return $created;
            }
    
            $start = $data['start_time'] ?? null;
            $end = $data['end_time'] ?? null;
            $desc = $data['task_description'] ?? null;
    
            $totalHours = null;
            if ($start && $end) {
                try {
                    $startC = Carbon::createFromFormat('H:i', $start);
                    $endC = Carbon::createFromFormat('H:i', $end);
                    $minutes = $endC->diffInMinutes($startC);
                    $totalHours = round($minutes / 60, 2);
                } catch (\Exception $e) {
                    $totalHours = null;
                }
            }
    
            $detail = AttendanceRequestOvertimeDetails::create([
                'request_id' => $data['request_id'],
                'overtime_type_id' => $data['overtime_type_id'] ?? null,
                'overtime_type' => $data['overtime_type'] ?? null,
                'task_description' => $desc,
                'date' => $data['date'] ?? null,
                'start_time' => $start,
                'end_time' => $end,
                'total_hours' => $totalHours,
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
