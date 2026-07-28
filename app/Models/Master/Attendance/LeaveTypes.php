<?php

namespace App\Models\Master\Attendance;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLeaveTypes
 * @property string $id
 * @property string|null $company_id
 * @property string $name
 * @property string|null $category
 * @property int $quota_days
 * @property string $reset_period
 * @property int $is_paid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereQuotaDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereResetPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveTypes whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LeaveTypes extends Model
{
    use HasUlids;

    protected $table = 'm_leave_types';

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'quota_days',
        'reset_period',
        'is_paid',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
