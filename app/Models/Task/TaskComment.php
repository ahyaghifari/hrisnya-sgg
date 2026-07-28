<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $task_id
 * @property string $user_id
 * @property string $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task\Task $task
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskComment whereUserId($value)
 * @mixin \Eloquent
 */
class TaskComment extends Model
{
    use HasUlids;

    protected $fillable = [
        'task_id',
        'user_id',
        'comment',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
