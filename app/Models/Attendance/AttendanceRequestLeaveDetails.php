<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRequestLeaveDetails
 * @property string $id
 * @property string $request_id
 * @property string|null $leave_type_id
 * @property string|null $leave_type
 * @property string|null $start_date
 * @property string|null $end_date
 * @property numeric|null $total_days
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereLeaveType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereLeaveTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereTotalDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestLeaveDetails whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AttendanceRequestLeaveDetails extends Model
{
    use HasUlids;

    protected $table = 'attendance_req_leave_details';

    protected $fillable = [
        'request_id',
        'leave_type_id',
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
    ];
}
