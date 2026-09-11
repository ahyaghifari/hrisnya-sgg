<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = keanggotaan regu 1 user di 1 jadwal untuk 1 BULAN (`period`, format 'Y-m') — bukan
 * riwayat tanggal-efektif bebas, disederhanakan karena regu biasanya diganti per bulan (rotasi).
 * Unique (schedule_id, user_id, period) - selalu pakai setForPeriod()/removeForPeriod(), jangan
 * create() langsung, supaya upsert-nya benar.
 */
class ScheduleShiftTeamMember extends Model
{
    use HasUlids;

    protected $table = 'schedule_shift_team_members';

    protected $fillable = [
        'team_id',
        'schedule_id',
        'user_id',
        'period',
        'created_by',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(ScheduleShiftTeam::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Regu user di jadwal ini untuk 1 bulan tertentu (format 'Y-m'), null kalau belum diatur. */
    public static function forPeriod(string $scheduleId, string $userId, string $period): ?self
    {
        return static::query()
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->where('period', $period)
            ->with('team')
            ->first();
    }

    /** Set/ganti regu user untuk 1 bulan — upsert, cuma menyentuh baris bulan itu. */
    public static function setForPeriod(Schedule $schedule, User $user, ScheduleShiftTeam $team, string $period, string $createdBy): self
    {
        return static::updateOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $user->id, 'period' => $period],
            ['team_id' => $team->id, 'created_by' => $createdBy]
        );
    }

    /** Lepas user dari regu apa pun ("Tanpa Regu") untuk 1 bulan tertentu. */
    public static function removeForPeriod(Schedule $schedule, User $user, string $period): void
    {
        static::where('schedule_id', $schedule->id)
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->delete();
    }
}
