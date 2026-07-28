<?php

namespace App\Models\Attendance;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRequests
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string|null $reason
 * @property string $status
 * @property string|null $approved_by
 * @property string|null $approved_at
 * @property string|null $cancelled_by
 * @property string|null $cancelled_at
 * @property int $is_flow_changed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalHistories> $approvalHistories
 * @property-read int|null $approval_histories_count
 * @property-read ApprovalSnapshots|null $approvalSnapshot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance\AttendanceRequestAttachments> $attachments
 * @property-read int|null $attachments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance\AttendanceRequestLeaveDetails> $leaveDetails
 * @property-read int|null $leave_details_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance\AttendanceRequestOvertimeDetails> $overtimeDetails
 * @property-read int|null $overtime_details_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance\AttendanceRequestPermissionDetails> $permissionDetails
 * @property-read int|null $permission_details_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereCancelledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereIsFlowChanged($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequests whereUserId($value)
 * @mixin \Eloquent
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
