<?php

namespace App\Models\Attendance;

use App\Models\Shift\MasterShift;
use App\Models\Shift\ShiftScheduleAssignments;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRecords
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
