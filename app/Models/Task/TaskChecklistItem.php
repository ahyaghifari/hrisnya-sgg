<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $task_id
 * @property string|null $created_by
 * @property string $title
 * @property bool $is_done
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task\Task $task
 * @property-read \App\Models\User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem whereIsDone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskChecklistItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskChecklistItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'task_id',
        'created_by',
        'title',
        'is_done',
        'position',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
