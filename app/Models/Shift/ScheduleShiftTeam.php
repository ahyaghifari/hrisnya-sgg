<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Regu"/tim shift — pengelompokan karyawan di 1 Schedule (jadwal shift). Keanggotaan PER BULAN
 * ada di ScheduleShiftTeamMember, lihat komentar migration & model itu.
 */
class ScheduleShiftTeam extends Model
{
    use HasUlids;

    protected $table = 'schedule_shift_teams';

    protected $fillable = [
        'schedule_id',
        'name',
        'color',
        'created_by',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ScheduleShiftTeamMember::class, 'team_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Kode shift yang boleh dipakai regu ini (2026-09-13, instruksi user - Regu A tidak boleh
     * pinjam shift Regu B). KOSONG = tidak dibatasi (semua shift schedule ini boleh) - lihat
     * ScheduleShiftTeamMember::allowedShiftTypeIdsFor(), bukan berarti "tidak boleh apa-apa". */
    public function shiftTypes(): BelongsToMany
    {
        return $this->belongsToMany(ScheduleShiftType::class, 'schedule_shift_team_shift_types', 'team_id', 'shift_type_id');
    }

    /** @return \Illuminate\Support\Collection<int, User> Anggota regu ini di bulan $period ('Y-m', default bulan berjalan). */
    public function membersForPeriod(?string $period = null): \Illuminate\Support\Collection
    {
        $period ??= now()->format('Y-m');

        return User::whereIn('id', $this->members()->where('period', $period)->pluck('user_id'))
            ->orderByName()
            ->get();
    }
}
