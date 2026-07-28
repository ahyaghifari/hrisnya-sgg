<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApprovalHistories
 * @property string $id
 * @property string $snapshot_id
 * @property string|null $activity_id
 * @property string $action
 * @property string|null $actor_id
 * @property string|null $actor_name
 * @property string|null $note
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $requestable_type
 * @property string $requestable_id
 * @property-read Model|\Eloquent $requestable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereActorName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereRequestableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereRequestableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereSnapshotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalHistories whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ApprovalHistories extends Model
{
    use HasUlids;

    protected $table = 'approval_histories';

    protected $fillable = [
        'snapshot_id',
        'activity_id',
        'action',
        'actor_id',
        'actor_name',
        'note',
        'metadata',
        'requestable_type',
        'requestable_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function requestable()
    {
        return $this->morphTo();
    }
}
