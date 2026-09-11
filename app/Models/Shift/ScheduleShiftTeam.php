<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** @return \Illuminate\Support\Collection<int, User> Anggota regu ini di bulan $period ('Y-m', default bulan berjalan). */
    public function membersForPeriod(?string $period = null): \Illuminate\Support\Collection
    {
        $period ??= now()->format('Y-m');

        return User::whereIn('id', $this->members()->where('period', $period)->pluck('user_id'))
            ->orderBy('name')
            ->get();
    }
}
