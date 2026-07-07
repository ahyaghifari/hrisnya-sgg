<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperShiftTemplates
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
