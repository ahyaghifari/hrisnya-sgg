<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperMasterShift
 * @property string $id
 * @property string $company_id
 * @property string|null $code
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property int $is_overnight
 * @property string|null $org_structure_id
 * @property string|null $color
 * @property string|null $shift_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Companies $company
 * @property-read \App\Models\Master\OrgStructure|null $orgStructure
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereIsOvernight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereOrgStructureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereShiftType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShift withoutTrashed()
 * @mixin \Eloquent
 */
class MasterShift extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'm_shifts';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'is_overnight',
        'org_structure_id',
        'color',
        'shift_type',
    ];

    public function orgStructure()
    {
        return $this->belongsTo(\App\Models\Master\OrgStructure::class, 'org_structure_id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
