<?php

namespace App\Models\Task;

use App\Enum\Approval\ApprovalModuleEnum;
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
 * @property string $id
 * @property string $code
 * @property string $company_id
 * @property string|null $department_id
 * @property string|null $service_id
 * @property string|null $project_id
 * @property string|null $assigned_to
 * @property string|null $approved_by
 * @property string|null $requirement
 * @property int $is_self_assigned
 * @property bool $needs_review
 * @property bool $notify_creator_on_review
 * @property bool $notify_creator_on_complete
 * @property string|null $status
 * @property int|null $rating
 * @property string|null $rejected_reason
 * @property string|null $rejection_note_by
 * @property string|null $rejection_note_at
 * @property TaskPriority $priority
 * @property int $position
 * @property int $total_cost
 * @property int $unit_cost
 * @property string|null $due_date
 * @property string|null $responded_date
 * @property string|null $started_date
 * @property string|null $completed_date
 * @property int $is_overdue
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalHistories> $approvalHistories
 * @property-read int|null $approval_histories_count
 * @property-read ApprovalSnapshots|null $approvalSnapshot
 * @property-read User|null $approvedBy
 * @property-read User|null $assignedTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\TaskAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\TaskChecklistItem> $checklistItems
 * @property-read int|null $checklist_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\TaskComment> $comments
 * @property-read int|null $comments_count
 * @property-read Companies $company
 * @property-read User|null $createdBy
 * @property-read int|null $duration
 * @property-read int|null $overdue_days
 * @property-read \App\Models\Task\TaskProject|null $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\TaskProof> $proofs
 * @property-read int|null $proofs_count
 * @property-read User|null $rejectionNoteBy
 * @property-read TaskService|null $service
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCompletedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereIsOverdue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereIsSelfAssigned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRejectedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRejectionNoteAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRejectionNoteBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRequirement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereRespondedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStartedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereTotalCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUnitCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUpdatedAt($value)
 * @mixin \Eloquent
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
        'list_id',
        'assigned_to',
        'approved_by',
        'requirement',
        'is_self_assigned',
        'needs_review',
        'notify_creator_on_review',
        'notify_creator_on_complete',
        'status',
        'rating',
        'priority',
        'position',
        'rejected_reason',
        'rejection_note_by',
        'rejection_note_at',
        'total_cost',
        'unit_cost',
        'due_date',
        'responded_date',
        'started_date',
        'completed_date',
        'is_overdue',
        'created_by',
    ];

    // status sengaja TIDAK di-cast ke enum — banyak tempat (Filament infolist, query builder,
    // ApprovalFlowService) sudah nyimpen/baca sebagai string mentah, cast enum bakal mecahin
    // TaskStatus::tryFrom($state) yang butuh string bukan object enum.
    // due_date/responded_date/started_date/completed_date sengaja TIDAK di-cast ke date - format
    // tanggal di view portal pakai Carbon::parse() manual, bukan mengandalkan cast model.
    protected $casts = [
        'priority' => TaskPriority::class,
        'needs_review' => 'boolean',
        'notify_creator_on_review' => 'boolean',
        'notify_creator_on_complete' => 'boolean',
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

    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    public function rejectionNoteBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejection_note_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalSnapshot(): HasOne
    {
        return $this->hasOne(ApprovalSnapshots::class,'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::TASK_REQUEST->value);
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
