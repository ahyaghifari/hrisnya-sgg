<?php

namespace App\Models\Shift;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperScheduleChangeLog
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
