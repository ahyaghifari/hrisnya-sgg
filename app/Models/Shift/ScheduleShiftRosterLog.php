<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan roster harian (schedule_shift_assignments), 1 baris = 1 sel (user_id+date)
 * yang berubah - lihat migration create_schedule_shift_roster_logs_table buat konteks lengkap.
 *
 * @property string $id
 * @property string $schedule_id
 * @property string $user_id
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string $source
 * @property string|null $changed_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Schedule $schedule
 * @property-read User $user
 * @property-read User|null $changedBy
 */
class ScheduleShiftRosterLog extends Model
{
    use HasUlids;

    protected $table = 'schedule_shift_roster_logs';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'date',
        'old_value',
        'new_value',
        'source',
        'changed_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
