<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApprovalActivities
 * @property string $id
 * @property string $snapshot_id
 * @property int|null $current_step_order
 * @property string|null $approver_type
 * @property string|null $approver_ref_id
 * @property string|null $scope_type
 * @property string|null $scope_ref_id
 * @property string $status
 * @property string|null $note
 * @property string|null $acted_by
 * @property string|null $acted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ApprovalManagement\ApprovalSnapshots $snapshot
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereActedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereActedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereApproverRefId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereApproverType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereCurrentStepOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereScopeRefId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereScopeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereSnapshotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalActivities whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ApprovalActivities extends Model
{
    use HasUlids;

    protected $table = 'approval_activities';

    protected $fillable = [
        'snapshot_id',
        'current_step_order',
        'approver_type',
        'approver_ref_id',
        'scope_type',
        'scope_ref_id',
        'status',
        'note',
        'acted_by',
        'acted_at',
    ];

    public function snapshot()
    {
        return $this->belongsTo(ApprovalSnapshots::class, 'snapshot_id', 'id');
    }
}
