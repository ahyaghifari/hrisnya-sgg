<?php

namespace App\Models\Attendance;

use App\Models\Master\Attendance\LeaveTypes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLeaveUserBalances
 * @property string $id
 * @property string $user_id
 * @property string $leave_type_id
 * @property string $period_start
 * @property string $period_end
 * @property int $quota
 * @property int $used
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read LeaveTypes $leaveType
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances whereLeaveTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances whereQuota($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances whereUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveUserBalances whereUserId($value)
 * @mixin \Eloquent
 */
class LeaveUserBalances extends Model
{
    use HasUlids;

    protected $table = 'leave_user_balances';

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'period_start',
        'period_end',
        'quota',
        'used',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveTypes::class, 'leave_type_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
