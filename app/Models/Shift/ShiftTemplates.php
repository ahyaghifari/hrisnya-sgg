<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperShiftTemplates
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property int $cycle_length
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Master\Companies $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ShiftTemplateDays> $days
 * @property-read int|null $days_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ShiftTemplateDays> $workDays
 * @property-read int|null $work_days_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereCycleLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplates withoutTrashed()
 * @mixin \Eloquent
 */
class ShiftTemplates extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'shift_templates';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'cycle_length',
    ];

    public function days()
    {
        return $this->hasMany(ShiftTemplateDays::class, 'shift_template_id')->orderBy('day_number');
    }

    public function workDays()
    {
        return $this->hasMany(ShiftTemplateDays::class, 'shift_template_id')
            ->where('is_off_day', false)
            ->orderBy('day_number');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Master\Companies::class, 'company_id');
    }
}
