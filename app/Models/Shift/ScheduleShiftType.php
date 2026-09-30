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

    /** Durasi shift dalam menit (rentang start_time-end_time apa adanya, tanpa dikurangi
     * break_minutes - ini "jam kerja" yang dimaksud user buat aturan tukar shift, sama dengan
     * label yang ditampilkan ke karyawan mis. "PAGI 08:00-14:00"). Nambah 1 hari kalau end_time
     * <= start_time (shift overnight, mis. Malam 20:00-08:00) supaya hasilnya tetap durasi
     * positif yang benar, bukan negatif. */
    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        $start = \Carbon\Carbon::createFromFormat('H:i:s', $this->start_time);
        $end = \Carbon\Carbon::createFromFormat('H:i:s', $this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end->diffInMinutes($start);
    }
}
