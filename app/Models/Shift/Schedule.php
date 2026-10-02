<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'allow_oncall_extra_shift',
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
        'allow_oncall_extra_shift' => 'boolean',
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

    /** Pengisi Jadwal tambahan selain Kepala Ruangan (tabel schedule_editors). */
    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'schedule_editors', 'schedule_id', 'user_id')->withTimestamps();
    }

    /** Kepala Ruangan ATAU Pengisi Jadwal - dipakai semua gerbang "seperti karu". */
    public function isManagedBy(?string $userId): bool
    {
        return $userId !== null
            && ($this->head_user_id === $userId || $this->editors()->whereKey($userId)->exists());
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
