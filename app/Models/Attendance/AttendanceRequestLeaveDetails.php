<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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

    public function request()
    {
        return $this->belongsTo(AttendanceRequests::class, 'request_id');
    }
}
