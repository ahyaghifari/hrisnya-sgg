<?php

namespace App\Models\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperShiftSwapRequests
 * @property string $id
 * @property string $company_id
 * @property string $requester_id
 * @property string $target_id
 * @property string $req_schedule_id
 * @property string|null $target_schedule_id
 * @property \Illuminate\Support\Carbon $req_date
 * @property \Illuminate\Support\Carbon $target_date
 * @property string $status
 * @property string|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $rejected_by
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalHistories> $approvalHistories
 * @property-read int|null $approval_histories_count
 * @property-read ApprovalSnapshots|null $approvalSnapshot
 * @property-read User|null $approver
 * @property-read User|null $rejecter
 * @property-read \App\Models\Shift\ShiftScheduleAssignments $reqSchedule
 * @property-read User|null $requester
 * @property-read User|null $target
 * @property-read \App\Models\Shift\ShiftScheduleAssignments|null $targetSchedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereRejectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereRejectedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereReqDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereReqScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereRequesterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereTargetDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereTargetScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequests withoutTrashed()
 * @mixin \Eloquent
 */
class ShiftSwapRequests extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;

    protected $table = 'shift_swap_requests';

    protected $fillable = [
        'company_id',
        'requester_id',
        'target_id',
        'req_schedule_id',
        'target_schedule_id',
        'req_date',
        'target_date',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'notes',
    ];

    protected $casts = [
        'req_date' => 'date',
        'target_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function reqSchedule()
    {
        return $this->belongsTo(ShiftScheduleAssignments::class, 'req_schedule_id');
    }

    public function targetSchedule()
    {
        return $this->belongsTo(ShiftScheduleAssignments::class, 'target_schedule_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value);
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id', 'id')
            ->where('requestable_type', ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value);
    }
}
