<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = 1 hari (0=Minggu.
 *
 * .6=Sabtu) untuk `Schedule` tipe FIXED. Lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md §2.2.
 *
 * @property string $id
 * @property string $schedule_id
 * @property int $day_of_week 0=Minggu..6=Sabtu (Carbon::dayOfWeek) - 1 baris per hari per schedule_id.
 * @property bool $is_off_day Hari libur - kalau true, seluruh kolom jam/toleransi di baris ini diabaikan (tidak dihitung telat/lembur). Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property string|null $start_time Jam mulai kerja hari ini. Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $break_minutes Durasi istirahat (menit), dikurangkan dari total jam kerja.
 * @property string|null $end_time Jam selesai kerja hari ini. Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $overtime_before_min Lembur SEBELUM jam masuk: minimal menit check-in lebih awal supaya dianggap lembur. Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $overtime_before_break Lembur sebelum jam masuk: jeda/gap (menit) sebelum mulai dihitung lembur.
 * @property int $overtime_before_max Lembur sebelum jam masuk: batas maksimal menit yang dihitung.
 * @property int $overtime_after_min Lembur SETELAH jam pulang: minimal menit check-out lebih lambat supaya dianggap lembur. Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $overtime_after_break Lembur setelah jam pulang: jeda/gap (menit) sebelum mulai dihitung lembur.
 * @property int $overtime_after_max Lembur setelah jam pulang: batas maksimal menit yang dihitung.
 * @property int $checkin_buffer_minutes Batas paling awal boleh scan masuk = jam_mulai - buffer ini (menit), scan sebelum itu dianggap di luar jendela valid. Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $checkout_buffer_minutes Batas paling akhir boleh scan pulang = jam_selesai + buffer ini (menit). Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $late_tolerance_minutes Toleransi keterlambatan (menit) sebelum check-in dianggap telat. Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $early_leave_tolerance_minutes Toleransi pulang cepat (menit) sebelum check-out dianggap pulang cepat. Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.
 * @property int $counted_work_days Bobot hari ini dihitung sebagai berapa hari kerja untuk rekap (biasanya 1). Belum dipakai calculator manapun per 2026-07-22.
 * @property int|null $max_work_minutes Batas maksimal jam kerja yang dihitung (menit) - lebih dari ini dipotong, tidak nambah working_minutes.
 * @property int $half_day_threshold_minutes Ambang jam kerja (menit) - di bawah ini dianggap setengah hari kalau >0. BELUM dipakai calculator manapun per 2026-07-22 (AttendanceStatus enum belum ada case setengah-hari).
 * @property int $absent_threshold_minutes Ambang jam kerja (menit) - di bawah ini dianggap alpha meski sudah scan, kalau >0. BELUM dipakai calculator manapun per 2026-07-22.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Shift\Schedule|null $schedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereAbsentThresholdMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereBreakMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereCheckinBufferMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereCheckoutBufferMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereCountedWorkDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereEarlyLeaveToleranceMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereHalfDayThresholdMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereIsOffDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereLateToleranceMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereMaxWorkMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereOvertimeAfterBreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereOvertimeAfterMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereOvertimeAfterMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereOvertimeBeforeBreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereOvertimeBeforeMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereOvertimeBeforeMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFixedDay whereUpdatedAt($value)
 * @mixin \Eloquent
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
