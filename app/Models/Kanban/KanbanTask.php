<?php

namespace App\Models\Kanban;

use App\Models\Task\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KanbanTask extends Model
{
    use HasUlids, SoftDeletes;

    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_REVIEW = 'review';
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REVIEW,
        self::STATUS_DONE,
    ];

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'board_id',
        'title',
        'description',
        'created_by',
        'assigned_to',
        'status',
        'due_date',
        'position',
        'priority',
        'completed_at',
        'related_task_id',
    ];

    // Eloquent tidak re-fetch row setelah insert (PK-nya ULID, bukan auto-increment), jadi
    // default DB ('todo'/'medium' di migration) tidak kebaca di object hasil create() kalau
    // kolomnya tidak diisi eksplisit — didefinisikan juga di sini supaya konsisten.
    protected $attributes = [
        'status' => self::STATUS_TODO,
        'priority' => self::PRIORITY_MEDIUM,
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function relatedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'related_task_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(KanbanTaskMember::class, 'task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(KanbanTaskComment::class, 'task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(KanbanTaskAttachment::class, 'task_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KanbanTaskItem::class, 'task_id')->orderBy('position')->orderBy('created_at');
    }

    public function isPersonal(): bool
    {
        return $this->board_id === null;
    }
}
