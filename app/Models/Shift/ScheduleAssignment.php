<?php

namespace App\Models\Shift;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assign Fixed/Flexible — dual-target: tepat 1 dari `user_id`/`job_position_id` harus terisi
 * (divalidasi di form/action pemanggil, BUKAN di model — belum ada UI yang menulis ke tabel ini
 * sesi ini, jadi belum ada tempat yang tepat buat validasi ini dipasang, lihat planning §2.6).
 *
 * Beda dari ScheduleShiftAssignment — tidak per-tanggal, cukup 1 baris per target dengan
 * riwayat lewat effective_from/effective_until.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $user_id
 * @property string|null $job_position_id
 * @property string $schedule_id
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_until
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies $company
 * @property-read User|null $creator
 * @property-read JobPositions|null $jobPosition
 * @property-read \App\Models\Shift\Schedule|null $schedule
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereEffectiveFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereEffectiveUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereJobPositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleAssignment whereUserId($value)
 * @mixin \Eloquent
 */
class ScheduleAssignment extends Model
{
    use HasUlids;

    protected $table = 'schedule_assignments';

    protected $fillable = [
        'company_id',
        'user_id',
        'job_position_id',
        'schedule_id',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolusi jadwal aktif user pada 1 tanggal — user spesifik menang (override personal),
     * fallback ke assignment jabatan kalau tidak ada. Lihat planning §2.6.
     */
    public static function resolveForUser(User $user, \DateTimeInterface|string $date): ?self
    {
        $date = (string) $date;

        $personal = static::query()
            ->where('user_id', $user->id)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->first();

        if ($personal) {
            return $personal;
        }

        $jobPositionId = $user->profile?->job_position_id;

        if (! $jobPositionId) {
            return null;
        }

        return static::query()
            ->where('job_position_id', $jobPositionId)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->first();
    }
}
