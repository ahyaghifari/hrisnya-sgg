<?php

namespace App\Models\Attendance;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AttendanceRequestCancellations extends Model
{
    use HasUlids;

    protected $table = 'attendance_request_cancellations';

    protected $fillable = [
        'attendance_request_id',
        'company_id',
        'requested_by',
        'reason',
        'status',
    ];

    public function attendanceRequest()
    {
        return $this->belongsTo(AttendanceRequests::class, 'attendance_request_id', 'id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    /** Alias requestedBy() — dipakai ApprovalTimelineService::build() yang generik butuh user(). */
    public function user()
    {
        return $this->requestedBy();
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::ATTENDANCE_REQUEST_CANCELLATION->value);
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id');
    }
}
