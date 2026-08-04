<?php

namespace App\Models\ApprovalManagement;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterApprovalFlows extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'm_approval_flows';

    protected $fillable = [
        'company_id',
        'name',
        'module',
        'flow_type',
        'description',
        'is_active',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'updated_at' => 'timestamp',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function steps()
    {
        return $this->hasMany(MasterApprovalFlowSteps::class, 'flow_id')->orderBy('step_order');
    }

    public function assignments()
    {
        return $this->hasMany(ApprovalFlowAssignments::class, 'approval_flow_id');
    }
}
