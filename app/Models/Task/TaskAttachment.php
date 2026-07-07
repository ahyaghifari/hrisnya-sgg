<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperTaskAttachment
 */
class TaskAttachment extends Model
{
    use HasUlids;

    protected $fillable = [
        'task_id',
        'file_path'
    ];

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
