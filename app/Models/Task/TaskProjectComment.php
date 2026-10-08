<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Diskusi Board. Extend TaskComment cuma buat pakai ulang bodyHtml() & relasi user (task() tidak dipakai). */
class TaskProjectComment extends TaskComment
{
    protected $table = 'task_project_comments';

    protected $fillable = ['project_id', 'user_id', 'parent_id', 'comment'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TaskProject::class, 'project_id');
    }
}
