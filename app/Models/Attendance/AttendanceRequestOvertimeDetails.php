<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRequestOvertimeDetails
 */
class AttendanceRequestOvertimeDetails extends Model
{
    use HasUlids;

    protected $table = 'attendance_req_overtime_details';

    protected $fillable = [
        'request_id',
        'overtime_type_id',
        'overtime_type',
        'task_description',
        'date',
        'start_time',
        'end_time',
        'total_hours',
    ];
}
