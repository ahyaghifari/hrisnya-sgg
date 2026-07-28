<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTaskReview
 * @property string $id
 * @property string|null $task_id
 * @property string|null $reviewer_id
 * @property string $status
 * @property int|null $rating
 * @property string|null $comment
 * @property string|null $rejected_note
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $reviewer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereDecidedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereRejectedNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereReviewerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReview whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskReview extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'task_id',
        'reviewer_id',
        'status',
        'rating',
        'comment',
        'rejected_note',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
