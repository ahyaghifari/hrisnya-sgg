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
 *
 * @property string $id
 * @property string $schedule_id
 * @property int $day_of_week 0=Minggu..6=Sabtu (Carbon::dayOfWeek) - 1 baris per hari per schedule_id.
 * @property bool $is_off_day Hari libur - kalau true, seluruh kolom target/toleransi di baris ini diabaikan. Referensi: knowledge/absensi/KONFIGURASI JADWAL FLEXIBLE.png, CONTOH JADWAL FLEXIBEL.png.
 * @property int $break_minutes Durasi istirahat (menit), dikurangkan dari total jam kerja.
 * @property int $min_work_minutes Target MINIMAL jam kerja (menit) per hari - di bawah ini dianggap kurang dari target. Referensi: knowledge/absensi/KONFIGURASI JADWAL FLEXIBLE.png, CONTOH JADWAL FLEXIBEL.png.
 * @property int $max_work_minutes PENTING beda makna dari Fixed/Shift: ini target jam kerja NORMAL (dipasangkan dengan min_work_minutes), bukan batas mutlak (kolom batas mutlaknya, max_work_minutes_cap, sudah dihapus - migration 2026_07_28_190000). Referensi: knowledge/absensi/KONFIGURASI JADWAL FLEXIBLE.png, CONTOH JADWAL FLEXIBEL.png.
 * @property int $overtime_break_minutes Lembur Flexible: jeda/gap (menit) sebelum mulai dihitung lembur (kerja di atas max_work_minutes target).
 * @property int $overtime_min_minutes Lembur Flexible: minimal menit kerja di atas target supaya dianggap lembur.
 * @property int $overtime_max_minutes Lembur Flexible: batas maksimal menit lembur yang dihitung.
 * @property int $counted_work_days Bobot hari ini dihitung sebagai berapa hari kerja untuk rekap (biasanya 1). Belum dipakai calculator manapun per 2026-07-22.
 * @property int $half_day_threshold_minutes Ambang jam kerja (menit) - di bawah ini dianggap setengah hari kalau >0. BELUM dipakai calculator manapun per 2026-07-22 (AttendanceStatus enum belum ada case setengah-hari).
 * @property \Illuminate\Support\Carbon|null $late_checkin_deadline Batas jam masuk (per hari) - kalau diisi dan check-in lewat dari jam ini, dianggap telat. Nullable = tidak ada batas untuk hari itu. Lihat FlexibleScheduleCalculator.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Shift\Schedule|null $schedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereBreakMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereCountedWorkDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereHalfDayThresholdMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereIsOffDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereLateCheckinDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereMaxWorkMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereMinWorkMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereOvertimeBreakMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereOvertimeMaxMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereOvertimeMinMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleFlexibleDay whereUpdatedAt($value)
 * @mixin \Eloquent
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
