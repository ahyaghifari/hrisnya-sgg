<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $project_id
 * @property string $user_id
 * @property string $role
 * @property bool $is_assigner
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Task\TaskProject|null $project
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember whereIsAssigner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProjectMember whereUserId($value)
 * @mixin \Eloquent
 */
class TaskProjectMember extends Model
{
    use HasUlids;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'is_assigner',
    ];

    protected $casts = [
        'is_assigner' => 'boolean',
    ];

    /** Owner otomatis boleh assign — is_assigner cuma relevan buat member biasa. */
    public function canAssign(): bool
    {
        return $this->role === self::ROLE_OWNER || $this->is_assigner;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TaskProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
