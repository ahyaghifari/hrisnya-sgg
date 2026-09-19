<?php

namespace App\Models\Task;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Task\TaskPriority;
use App\Enum\Task\TaskStatus;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Task extends Model
{
    use HasUlids;

    protected $table = 'task_records';
    protected $fillable = [
        'code',
        'company_id',
        'department_id',
        'project_id',
        'list_id',
        'assigned_to',
        'approved_by',
        'requirement',
        'description',
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
        'reviewer_id',
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Siapa yang boleh kasih rating - kalau null, fallback ke createdBy (lihat
     * TaskService::rateTask()). Cuma relevan untuk task di dalam Project. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
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

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class, 'task_id')->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id')->orderBy('created_at');
    }

    /**
     * Apakah $userId WAJIB memberi rating bintang untuk tugas ini: tugas sudah Selesai, belum dinilai,
     * bukan catatan pribadi (Todo Saya), dan dia penilainya (reviewer ?? pembuat tugas) - tapi bukan
     * pelaksananya sendiri (menilai diri sendiri tidak ada artinya). Tugas tanpa reviewer dan tidak
     * wajib review langsung selesai begitu Selesai - tanpa penilaian bintang.
     */
    public function requiresRatingFrom(?string $userId): bool
    {
        $raterId = $this->reviewer_id ?? $this->created_by;

        return $userId !== null
            && $this->status === TaskStatus::COMPLETE->value
            && ! $this->rating
            && ($this->reviewer_id !== null || $this->needs_review)
            && ! ($this->is_self_assigned && ! $this->project_id)
            && $raterId === $userId
            && $raterId !== $this->assigned_to;
    }
}
