<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pola rotasi tipe SHIFT — pengganti `ShiftTemplates`. Terikat ke 1 `Schedule` (dulu cuma
 * company_id) supaya jelas kode-kode mana yang boleh dipakai template ini. Cutover kode yang
 * MEMAKAI template (GenerateScheduleFromTemplate) ditunda ke Tahap 2.
 */
class ScheduleShiftTemplate extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'schedule_shift_templates';

    protected $fillable = [
        'schedule_id',
        'name',
        'description',
        'cycle_length',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(ScheduleShiftTemplateDay::class, 'schedule_shift_template_id')->orderBy('day_number');
    }

    public function workDays(): HasMany
    {
        return $this->hasMany(ScheduleShiftTemplateDay::class, 'schedule_shift_template_id')
            ->where('is_off_day', false)
            ->orderBy('day_number');
    }

    public function templateAssignments(): HasMany
    {
        return $this->hasMany(ScheduleShiftTemplateAssignment::class, 'shift_template_id');
    }
}
