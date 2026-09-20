<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Header/master jadwal — ganti `MasterShift` (restrukturisasi 2026-07-18, lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md). 1 baris = 1 grup jadwal
 * (Fixed/Flexible/Shift, lihat ScheduleType), isi konkretnya ada di tabel anak sesuai
 * schedule_type: fixedDays(), flexibleDays(), atau shiftTypes().
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
        'shift_pattern',
        'use_template',
        'use_teams',
        'team_group_mode',
        'color',
        'off_bg_color',
        'off_font_color',
        'head_user_id',
        'flexible_checkin_from',
        'flexible_checkin_until',
    ];

    protected $casts = [
        'use_template' => 'boolean',
        'use_teams' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    /** "Kepala Ruangan" - dipakai TeamShiftScheduleCard buat resolve link "Detail Jadwal" ke
     * Schedule yang benar (lihat migration 2026_08_19_100000). Nullable, jadwal lama boleh
     * kosong. */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
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
