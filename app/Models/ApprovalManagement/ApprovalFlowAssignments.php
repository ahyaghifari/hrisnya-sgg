<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
