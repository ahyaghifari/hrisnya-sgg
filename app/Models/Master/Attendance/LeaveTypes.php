<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLeaveTypes
 */
class LeaveTypes extends Model
{
    use HasUlids;

    protected $table = 'm_leave_types';

    protected $fillable = [
        'company_id',
        'name',
        'quota_days',
        'reset_period',
        'is_paid',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
