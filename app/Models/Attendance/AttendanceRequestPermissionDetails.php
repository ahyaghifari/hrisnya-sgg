<?php

namespace App\Models\Attendance;

use App\Models\Master\Attendance\PermissionTypes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAttendanceRequestPermissionDetails
 * @property string $id
 * @property string $request_id
 * @property string|null $permission_type_id
 * @property string|null $permission_type
 * @property string|null $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $purpose
 * @property string|null $location
 * @property string|null $substitute_user_id
 * @property string|null $substitute_user_name
 * @property string|null $substitute_task
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Attendance\AttendanceRequests $attendanceRequest
 * @property-read PermissionTypes|null $permissionType
 * @property-read \App\Models\User|null $substituteUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails wherePermissionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails wherePermissionTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails wherePurpose($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereSubstituteTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereSubstituteUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereSubstituteUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceRequestPermissionDetails whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AttendanceRequestPermissionDetails extends Model
{
    use HasUlids;

    protected $table = 'attendance_req_permission_details';

    protected $fillable = [
        'request_id',
        'permission_type_id',
        'permission_type',
        'date',
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

    public function permissionType()
    {
        return $this->belongsTo(PermissionTypes::class, 'permission_type_id', 'id');
    }

    public function substituteUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'substitute_user_id', 'id');
    }
}
