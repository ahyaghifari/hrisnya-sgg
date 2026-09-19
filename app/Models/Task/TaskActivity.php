<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris log aktivitas pada sebuah tugas (pindah List, ubah status, ceklis, rating, dst). */
class TaskActivity extends Model
{
    use HasUlids;

    protected $fillable = ['task_id', 'user_id', 'action', 'description', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
