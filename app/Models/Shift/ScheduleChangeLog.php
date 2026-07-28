<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperScheduleChangeLog
 * @property string $id
 * @property string $schedule_id
 * @property string|null $changed_by
 * @property array<array-key, mixed> $snapshot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $changedBy
 * @property-read \App\Models\Shift\Schedule|null $schedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog whereChangedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog whereSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleChangeLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ScheduleChangeLog extends Model
{
    use HasUlids;

    protected $table = 'schedule_change_logs';

    protected $fillable = [
        'schedule_id',
        'changed_by',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
