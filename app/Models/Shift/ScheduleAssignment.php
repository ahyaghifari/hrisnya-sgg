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
 * Beda dari ScheduleShiftAssignment — tidak per-tanggal, cukup 1 baris per target dengan
 * riwayat lewat effective_from/effective_until.
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
