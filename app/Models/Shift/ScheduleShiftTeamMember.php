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

    /** Carry-over Tim bulan DEPAN (relatif ke bulan berjalan ASLI) dari bulan berjalan, LANGSUNG
     * disimpan (upsert, idempotent) - dipanggil dari tab Regu MAUPUN tab Detail Penjadwalan
     * (instruksi user 2026-09-16: siapa pun yang buka bulan depan duluan yang men-trigger, tidak
     * boleh tergantung urutan buka tab). No-op kalau $period BUKAN bulan depan. Karyawan yang
     * bulan berjalannya juga belum punya Tim dilewati (carry-over butuh sumber, tidak bikin baris
     * kosong) - jadi otomatis no-op juga kalau schedule ini memang belum pernah pakai Tim. */
    public static function autoCarryNextMonthFromCurrent(Schedule $schedule, iterable $employees, string $period, string $actorId): void
    {
        if ($period !== now()->addMonth()->format('Y-m')) {
            return;
        }

        $currentMonth = now()->format('Y-m');

        foreach ($employees as $user) {
            if (static::forPeriod($schedule->id, $user->id, $period)) {
                continue;
            }

            $carryFrom = static::forPeriod($schedule->id, $user->id, $currentMonth);

            if ($carryFrom) {
                static::setForPeriod($schedule, $user, $carryFrom->team, $period, $actorId);
            }
        }
    }

    /** Lepas user dari regu apa pun ("Tanpa Regu") untuk 1 bulan tertentu. */
    public static function removeForPeriod(Schedule $schedule, User $user, string $period): void
    {
        static::where('schedule_id', $schedule->id)
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->delete();
    }

    /** Kode shift yang boleh diisi buat 1 user di 1 bulan tertentu (2026-09-13, instruksi user -
     * regu tidak boleh pinjam shift regu lain) - null = TIDAK dibatasi (Regu tidak aktif, user
     * belum punya regu bulan itu, atau regu-nya belum diatur daftar shift-nya), dipakai
     * ScheduleShiftRosterTab & ScheduleShiftRotationTab supaya aturannya satu tempat, tidak
     * dobel-tulis di 2 komponen.
     *
     * @return array<int, string>|null
     */
    public static function allowedShiftTypeIdsFor(Schedule $schedule, string $userId, string $period): ?array
    {
        if (! $schedule->use_teams) {
            return null;
        }

        $team = static::forPeriod($schedule->id, $userId, $period)?->team;

        if (! $team) {
            return null;
        }

        $ids = $team->shiftTypes()->pluck('schedule_shift_types.id')->all();

        return $ids ?: null;
    }
}
