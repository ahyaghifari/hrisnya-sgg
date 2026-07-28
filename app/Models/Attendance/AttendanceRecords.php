<?php

namespace App\Models\Attendance;

use App\Models\Shift\MasterShift;
use App\Models\Shift\ShiftScheduleAssignments;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRecords
 * @property string $id
 * @property string|null $user_id
 * @property string $nip
 * @property string $date
 * @property string|null $check_in_time
 * @property string|null $check_out_time
 * @property numeric|null $working_minutes
 * @property float|null $late_minutes
 * @property float|null $early_leave_minutes
 * @property float|null $overtime_minutes
 * @property numeric|null $work_day_weight
 * @property bool|null $meets_target
 * @property bool $out_of_window
 * @property float|null $permission_minutes
 * @property string|null $status
 * @property string|null $source
 * @property bool $is_auto_calculated
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $schedule_id
 * @property string|null $shift_id
 * @property-read ShiftScheduleAssignments|null $schedule
 * @property-read MasterShift|null $shift
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereCheckInTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereCheckOutTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereEarlyLeaveMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereIsAutoCalculated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereLateMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereMeetsTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereNip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereOutOfWindow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereOvertimeMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords wherePermissionMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereWorkDayWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRecords whereWorkingMinutes($value)
 * @mixin \Eloquent
 */
class AttendanceRecords extends Model
{
    use HasUlids;

    protected $table = 'attendance_records';

    protected $fillable = [
        'user_id',
        'nip',
        'date',
        'schedule_id',
        'shift_id',
        'check_in_time',
        'check_out_time',
        'working_minutes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'permission_minutes',
        'status',
        'source',
        'is_auto_calculated',
        'work_day_weight',
        'meets_target',
        'out_of_window',
    ];

    protected $casts = [
        'is_auto_calculated' => 'boolean',
        'work_day_weight'    => 'decimal:1',
        'meets_target'       => 'boolean',
        'out_of_window'      => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ShiftScheduleAssignments::class, 'schedule_id');
    }
}
