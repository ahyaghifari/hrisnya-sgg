<?php

namespace App\Models\Attendance;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRequests
 */
class AttendanceRequests extends Model
{
    use HasUlids;

    protected $table = 'attendance_requests';

    protected $fillable = [
        'user_id',
        'type',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'is_flow_changed',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function overtimeDetails()
    {
        return $this->hasMany(AttendanceRequestOvertimeDetails::class, 'request_id', 'id');
    }

    public function leaveDetails()
    {
        return $this->hasMany(AttendanceRequestLeaveDetails::class, 'request_id', 'id');
    }

    public function permissionDetails()
    {
        return $this->hasMany(AttendanceRequestPermissionDetails::class, 'request_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(AttendanceRequestAttachments::class, 'request_id', 'id');
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class,'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::ATTENDANCE_REQUEST->value);
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id');
    }
}
