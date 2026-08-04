<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
