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
