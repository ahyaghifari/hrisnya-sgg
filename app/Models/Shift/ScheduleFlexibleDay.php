<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = 1 hari (0=Minggu.
 *
 * .6=Sabtu) untuk `Schedule` tipe FLEXIBLE. Jendela absensi
 * (jam mulai/akhir bisa scan) ada di level Schedule, bukan di sini — lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md §2.3.
 */
class ScheduleFlexibleDay extends Model
{
    use HasUlids;

    protected $table = 'schedule_flexible_days';

    protected $fillable = [
        'schedule_id',
        'day_of_week',
        'is_off_day',
        'break_minutes',
        'min_work_minutes',
        'max_work_minutes',
        'overtime_break_minutes',
        'overtime_min_minutes',
        'overtime_max_minutes',
        'counted_work_days',
        'half_day_threshold_minutes',
        'late_checkin_deadline',
    ];

    protected $casts = [
        'is_off_day' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
}
