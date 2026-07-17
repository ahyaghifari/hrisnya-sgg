<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengganti `ShiftTemplateDays`. */
class ScheduleShiftTemplateDay extends Model
{
    use HasUlids;

    protected $table = 'schedule_shift_template_days';

    protected $fillable = [
        'schedule_shift_template_id',
        'day_number',
        'shift_type_id',
        'is_off_day',
    ];

    protected $casts = [
        'is_off_day' => 'boolean',
    ];

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftTemplate::class, 'schedule_shift_template_id');
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftType::class, 'shift_type_id');
    }
}
