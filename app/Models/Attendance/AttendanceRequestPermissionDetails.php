<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AttendanceRequestPermissionDetails extends Model
{
    use HasUlids;

    protected $table = 'attendance_req_permission_details';

    protected $fillable = [
        'request_id',
        'permission_type',
        'category',
        'date',
        'end_date',
        'total_days',
        'start_time',
        'end_time',
        'purpose',
        'location',
        'substitute_user_id',
        'substitute_user_name',
        'substitute_task',
    ];

    public function attendanceRequest()
    {
        return $this->belongsTo(AttendanceRequests::class, 'request_id', 'id');
    }

    public function substituteUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'substitute_user_id', 'id');
    }
}
