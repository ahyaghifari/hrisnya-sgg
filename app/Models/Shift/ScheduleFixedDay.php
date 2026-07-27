<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = 1 hari (0=Minggu..6=Sabtu) untuk `Schedule` tipe FIXED. Lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md §2.2.
 */
class ScheduleFixedDay extends Model
{
    use HasUlids;

    protected $table = 'schedule_fixed_days';

    protected $fillable = [
        'schedule_id',
        'day_of_week',
        'is_off_day',
        'start_time',
        'break_minutes',
        'min_work_minutes',
        'end_time',
        'overtime_before_min',
        'overtime_before_break',
        'overtime_before_max',
        'overtime_after_min',
        'overtime_after_break',
        'overtime_after_max',
        'checkin_buffer_minutes',
        'checkout_buffer_minutes',
        'late_tolerance_minutes',
        'early_leave_tolerance_minutes',
        'counted_work_days',
        'max_work_minutes',
        'half_day_threshold_minutes',
        'absent_threshold_minutes',
    ];

    protected $casts = [
        'is_off_day' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
}
