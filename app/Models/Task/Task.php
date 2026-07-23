<?php

namespace App\Models\Task;

use App\Enum\Task\TaskPriority;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Master\Companies;
use App\Models\Master\Task\TaskService;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @mixin IdeHelperTask
 */
class Task extends Model
{
    use HasUlids;

    protected $table = 'task_records';
    protected $fillable = [
        'code',
        'company_id',
        'department_id',
        'service_id',
        'project_id',
        'assigned_to',
        'approved_by',
        'sla_reviewer_id',
        'requirement',
        'is_self_assigned',
        'status',
        'priority',
        'position',
        'rejected_reason',
        'risk_level',
        'total_cost',
        'unit_cost',
        'due_date',
        'responded_date',
        'started_date',
        'completed_date',
        'is_overdue',
        'collaboration_note',
        'root_cause',
        'solution',
        'created_by',
    ];

    // status sengaja TIDAK di-cast ke enum — banyak tempat (Filament infolist, query builder,
    // ApprovalFlowService) sudah nyimpen/baca sebagai string mentah, cast enum bakal mecahin
    // TaskStatus::tryFrom($state) yang butuh string bukan object enum.
    // due_date/responded_date/started_date/completed_date sengaja TIDAK di-cast ke date —
    // App\Http\Resources\Task\TaskDetailResource & TaskListResource (dipakai mobile API)
    // nge-return kolom ini mentah-mentah; cast bakal ubah format JSON-nya jadi ISO datetime,
    // beda kontrak dari yang mobile app sudah biasa terima. Format tanggal di view portal
    // pakai Carbon::parse() manual, bukan mengandalkan cast model.
    protected $casts = [
        'priority' => TaskPriority::class,
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'task_id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(TaskProof::class, 'task_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TaskService::class, 'service_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TaskProject::class, 'project_id');
    }

    public function slaReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sla_reviewer_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(TaskReview::class, 'task_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalSnapshot(): HasOne
    {
        return $this->hasOne(ApprovalSnapshots::class,'module_id', 'id')
            ->where('module_type', 'task_request');
    }

    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistories::class, 'requestable');
    }

    public function getDurationAttribute(): ?int
    {
        if (! $this->responded_date || ! $this->completed_date) {
            return null;
        }

        $start = Carbon::parse($this->responded_date);
        $end = Carbon::parse($this->completed_date);

        return $start->diffInDays($end);
    }

    public function getOverdueDaysAttribute(): ?int
    {
        if (! $this->due_date || ! $this->completed_date) {
            return null;
        }

        $completed = Carbon::parse($this->completed_date);
        $due = Carbon::parse($this->due_date);

        if ($completed->lte($due)) {
            return 0;
        }

        return $due->diffInDays($completed);
    }
    
    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'task_id')->orderBy('position')->orderBy('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id')->orderBy('created_at');
    }
}
