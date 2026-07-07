<?php

namespace App\Models\Attendance;

use App\Models\Master\Attendance\LeaveTypes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLeaveUserBalances
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
