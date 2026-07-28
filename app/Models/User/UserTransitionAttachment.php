<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperUserTransitionAttachment
 * @property string $id
 * @property string $user_transition_id
 * @property string $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $file_url
 * @property-read \App\Models\User\UserTransition $transition
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionAttachment whereUserTransitionId($value)
 * @mixin \Eloquent
 */
class UserTransitionAttachment extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_transition_id',
        'file_path',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(UserTransition::class, 'user_transition_id');
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
