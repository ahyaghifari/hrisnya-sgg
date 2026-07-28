<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Header/master jadwal — ganti `MasterShift` (restrukturisasi 2026-07-18, lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md). 1 baris = 1 grup jadwal
 * (Fixed/Flexible/Shift, lihat ScheduleType), isi konkretnya ada di tabel anak sesuai
 * schedule_type: fixedDays(), flexibleDays(), atau shiftTypes().
 *
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string $schedule_type fixed|flexible|shift (App\Enum\ScheduleType) - menentukan tabel anak mana yang relevan: schedule_fixed_days/schedule_flexible_days/schedule_shift_types. Pola & nama kolom niru fingerspot.io.
 * @property bool $use_template Saklar tipe Shift: kalau true, roster (schedule_shift_assignments) diisi via generate dari Template Shift (schedule_shift_rotations), bukan manual murni.
 * @property string|null $color
 * @property string|null $flexible_checkin_from Jendela jam boleh check-in untuk jadwal Flexible - 1 nilai per schedule (BUKAN per hari, beda dari schedule_flexible_days). Referensi: knowledge/absensi/KONFIGURASI JADWAL FLEXIBLE.png, CONTOH JADWAL FLEXIBEL.png.
 * @property string|null $flexible_checkin_until Batas akhir jendela check-in Flexible, lihat flexible_checkin_from. Referensi: knowledge/absensi/KONFIGURASI JADWAL FLEXIBLE.png, CONTOH JADWAL FLEXIBEL.png.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ScheduleAssignment> $assignments
 * @property-read int|null $assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ScheduleChangeLog> $changeLogs
 * @property-read int|null $change_logs_count
 * @property-read Companies $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ScheduleFixedDay> $fixedDays
 * @property-read int|null $fixed_days_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ScheduleFlexibleDay> $flexibleDays
 * @property-read int|null $flexible_days_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ScheduleShiftRotation> $rotations
 * @property-read int|null $rotations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift\ScheduleShiftType> $shiftTypes
 * @property-read int|null $shift_types_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereFlexibleCheckinFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereFlexibleCheckinUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereScheduleType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereUseTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule withoutTrashed()
 * @mixin \Eloquent
 */
class Schedule extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'schedules';

    protected $fillable = [
        'company_id',
        'name',
        'schedule_type',
        'use_template',
        'color',
        'flexible_checkin_from',
        'flexible_checkin_until',
    ];

    protected $casts = [
        'use_template' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function fixedDays(): HasMany
    {
        return $this->hasMany(ScheduleFixedDay::class, 'schedule_id');
    }

    public function flexibleDays(): HasMany
    {
        return $this->hasMany(ScheduleFlexibleDay::class, 'schedule_id');
    }

    public function shiftTypes(): HasMany
    {
        // orderBy id: ULID sortable lexically by waktu dibuat — jaga urutan baris shift tetap
        // sesuai urutan "+ Tambah Shift" ditekan, bukan urutan acak dari DB.
        return $this->hasMany(ScheduleShiftType::class, 'schedule_id')->orderBy('id');
    }

    public function rotations(): HasMany
    {
        return $this->hasMany(ScheduleShiftRotation::class, 'schedule_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class, 'schedule_id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ScheduleChangeLog::class, 'schedule_id');
    }
}
