<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMasterApprovalFlowSteps
 * @property string $id
 * @property string $flow_id
 * @property int $step_order
 * @property string $approver_type
 * @property string $approver_ref_id
 * @property string $approval_mode
 * @property string $scope_type
 * @property string|null $scope_ref_id
 * @property int $is_final
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ApprovalManagement\MasterApprovalFlows|null $flows
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereApprovalMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereApproverRefId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereApproverType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereFlowId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereIsFinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereScopeRefId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereScopeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereStepOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlowSteps whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MasterApprovalFlowSteps extends Model
{
    use HasUlids;

    protected $table = 'm_approval_flow_steps';

    protected $fillable = [
        'flow_id',
        'step_order',
        'approver_type',
        'approver_ref_id',
        'approval_mode',
        'scope_type',
        'scope_ref_id',
        'is_final',
    ];

    public function flows()
    {
        return $this->belongsTo(MasterApprovalFlows::class, 'flow_id');
    }
}
