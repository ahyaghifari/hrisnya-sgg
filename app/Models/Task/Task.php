<?php

namespace App\Models\Task;

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
        'assigned_to',
        'approved_by',
        'sla_reviewer_id',
        'requirement',
        'is_self_assigned',
        'status',
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
}
