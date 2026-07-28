<?php

namespace App\Models\Shift;

use App\Models\Master\Attendance\OvertimeTypes;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperShiftOvertimeCosts
 * @property string $id
 * @property string $shift_id
 * @property string|null $overtime_type_id
 * @property int|null $maximum_overtime_hours
 * @property numeric|null $overtime_rate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $company_id
 * @property-read Companies|null $company
 * @property-read OvertimeTypes|null $overtimeType
 * @property-read \App\Models\Shift\MasterShift|null $shift
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereMaximumOvertimeHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereOvertimeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereOvertimeTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftOvertimeCosts withoutTrashed()
 * @mixin \Eloquent
 */
class ShiftOvertimeCosts extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'shift_overtime_costs';

    protected $fillable = [
        'shift_id',
        'overtime_type_id',
        'company_id',
        'maximum_overtime_hours',
        'overtime_rate',
    ];

    public function shift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    public function overtimeType()
    {
        return $this->belongsTo(OvertimeTypes::class, 'overtime_type_id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
