<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperShiftTemplateDays
 * @property string $id
 * @property string $shift_template_id
 * @property int $day_number
 * @property string|null $shift_id
 * @property bool $is_off_day
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Shift\MasterShift|null $shift
 * @property-read \App\Models\Shift\ShiftTemplates|null $shiftTemplate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays whereDayNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays whereIsOffDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays whereShiftTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftTemplateDays whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ShiftTemplateDays extends Model
{
    use HasUlids;

    protected $table = 'shift_template_days';

    protected $fillable = [
        'shift_template_id',
        'day_number',
        'shift_id',
        'is_off_day',
    ];

    protected $casts = [
        'is_off_day' => 'boolean',
    ];

    public function shiftTemplate()
    {
        return $this->belongsTo(ShiftTemplates::class, 'shift_template_id');
    }

    public function shift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }
}
