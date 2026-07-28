<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRequestOvertimeDetails
 * @property string $id
 * @property string $request_id
 * @property string|null $overtime_type_id
 * @property string|null $overtime_type
 * @property string|null $task_description
 * @property string|null $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property numeric|null $total_hours
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Attendance\AttendanceRequests $attendanceRequest
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereOvertimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereOvertimeTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereTaskDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereTotalHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestOvertimeDetails whereUpdatedAt($value)
 * @mixin \Eloquent
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

    public function attendanceRequest()
    {
        return $this->belongsTo(AttendanceRequests::class, 'request_id', 'id');
    }
}
