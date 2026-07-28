<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperTaskAttachment
 * @property string $id
 * @property string|null $task_id
 * @property string $type
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $url
 * @property string|null $uploaded_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $file_url
 * @property-read User|null $uploadedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereUploadedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskAttachment whereUrl($value)
 * @mixin \Eloquent
 */
class TaskAttachment extends Model
{
    use HasUlids;

    public const TYPE_FILE = 'file';
    public const TYPE_LINK = 'link';

    protected $fillable = [
        'task_id',
        'type',
        'file_path',
        'file_name',
        'url',
        'uploaded_by',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isLink(): bool
    {
        return $this->type === self::TYPE_LINK;
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
