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
        'leave_type',
        'category',
        'start_date',
        'end_date',
        'total_days',
        'substitute_task',
    ];

    public function request()
    {
        return $this->belongsTo(AttendanceRequests::class, 'request_id');
    }

    /** Pengganti cuti - boleh lebih dari 1 orang (tabel attendance_req_leave_substitutes). */
    public function substitutes()
    {
        return $this->belongsToMany(\App\Models\User::class, 'attendance_req_leave_substitutes', 'leave_detail_id', 'user_id')->withTimestamps();
    }

}
