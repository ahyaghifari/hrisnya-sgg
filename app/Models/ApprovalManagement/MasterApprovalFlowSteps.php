<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMasterApprovalFlowSteps
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
