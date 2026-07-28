<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApprovalFlowAssignments
 * @property string $id
 * @property string $approval_flow_id
 * @property string $assignable_type
 * @property string $assignable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ApprovalManagement\MasterApprovalFlows|null $approvalFlow
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments whereApprovalFlowId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments whereAssignableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments whereAssignableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalFlowAssignments whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ApprovalFlowAssignments extends Model
{
    use HasUlids;

    protected $table = 'm_approval_flow_assignments';

    protected $fillable = [
        'approval_flow_id',
        'assignable_id',
        'assignable_type',
    ];

    public function approvalFlow()
    {
        return $this->belongsTo(MasterApprovalFlows::class, 'approval_flow_id');
    }
}
