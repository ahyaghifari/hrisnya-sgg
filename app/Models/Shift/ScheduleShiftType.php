<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kode shift (Pagi/Siang/Malam/dst) di bawah 1 `Schedule` tipe SHIFT — ganti isi baris
 * `MasterShift` lama. 8 kolom "Pengaturan Lanjutan" (checkin/checkout buffer, toleransi,
 * dst) belum dipakai logic manapun — skema disiapkan duluan, lihat planning §6.
 */
class ScheduleShiftType extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'schedule_shift_types';

    protected $fillable = [
        'schedule_id',
        'code',
        'name',
        'shift_type',
        'start_time',
        'break_minutes',
        'end_time',
        'is_overnight',
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
        'color',
        'font_color',
    ];

    protected $casts = [
        'is_overnight' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
}
