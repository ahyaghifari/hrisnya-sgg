<?php

namespace App\Models\Kanban;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanTaskItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'task_id',
        'title',
        'is_done',
        'position',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(KanbanTask::class, 'task_id');
    }
}
