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
 *
 * @property string $id
 * @property string $schedule_id
 * @property string $code
 * @property string $name
 * @property string|null $shift_type PAGI|SIANG|MALAM (App\Enum\ShiftType) - opsional, cuma label kategori tampilan, tidak mempengaruhi perhitungan.
 * @property string $start_time Jam mulai shift ini. Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property int $break_minutes Durasi istirahat (menit), dikurangkan dari total jam kerja.
 * @property string $end_time Jam selesai shift ini. Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property bool $is_overnight Shift melewati tengah malam (end_time < start_time secara jam) - kalau true, end_time dianggap +1 hari saat hitung durasi.
 * @property int $overtime_before_min Lembur SEBELUM jam masuk: minimal menit check-in lebih awal supaya dianggap lembur. Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property int $overtime_before_break Lembur sebelum jam masuk: jeda/gap (menit) sebelum mulai dihitung lembur.
 * @property int $overtime_before_max Lembur sebelum jam masuk: batas maksimal menit yang dihitung.
 * @property int $overtime_after_min Lembur SETELAH jam pulang: minimal menit check-out lebih lambat supaya dianggap lembur. Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property int $overtime_after_break Lembur setelah jam pulang: jeda/gap (menit) sebelum mulai dihitung lembur.
 * @property int $overtime_after_max Lembur setelah jam pulang: batas maksimal menit yang dihitung.
 * @property int $checkin_buffer_minutes Batas paling awal boleh scan masuk = jam_mulai - buffer ini (menit), scan sebelum itu dianggap di luar jendela valid. Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property int $checkout_buffer_minutes Batas paling akhir boleh scan pulang = jam_selesai + buffer ini (menit). Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property int $late_tolerance_minutes Toleransi keterlambatan (menit) sebelum check-in dianggap telat. Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property int $early_leave_tolerance_minutes Toleransi pulang cepat (menit) sebelum check-out dianggap pulang cepat. Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.
 * @property int $counted_work_days Bobot hari ini dihitung sebagai berapa hari kerja untuk rekap (biasanya 1). Belum dipakai calculator manapun per 2026-07-22.
 * @property int|null $max_work_minutes Batas maksimal jam kerja yang dihitung (menit) - lebih dari ini dipotong, tidak nambah working_minutes.
 * @property int $half_day_threshold_minutes Ambang jam kerja (menit) - di bawah ini dianggap setengah hari kalau >0. BELUM dipakai calculator manapun per 2026-07-22 (AttendanceStatus enum belum ada case setengah-hari).
 * @property int $absent_threshold_minutes Ambang jam kerja (menit) - di bawah ini dianggap alpha meski sudah scan, kalau >0. BELUM dipakai calculator manapun per 2026-07-22.
 * @property string|null $color Warna background solid buat tampilan kode shift ini di UI - dipakai APA ADANYA, tidak diadaptasi tema gelap/terang.
 * @property string|null $font_color Warna teks solid pasangan color, dipilih manual supaya kontras - juga tidak diadaptasi tema.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Shift\Schedule|null $schedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereAbsentThresholdMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereBreakMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereCheckinBufferMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereCheckoutBufferMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereCountedWorkDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereEarlyLeaveToleranceMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereFontColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereHalfDayThresholdMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereIsOvernight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereLateToleranceMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereMaxWorkMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereOvertimeAfterBreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereOvertimeAfterMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereOvertimeAfterMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereOvertimeBeforeBreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereOvertimeBeforeMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereOvertimeBeforeMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereShiftType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleShiftType withoutTrashed()
 * @mixin \Eloquent
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
