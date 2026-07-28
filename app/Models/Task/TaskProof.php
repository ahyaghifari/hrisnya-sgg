<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperTaskProof
 * @property string $id
 * @property string|null $task_id
 * @property string|null $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $file_url
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof whereTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProof whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskProof extends Model
{
    use HasUlids;

    protected $fillable = [
        'task_id',
        'file_path',
    ];

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
