<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ApprovalSnapshots extends Model
{
    use HasUlids;
    protected $table = 'approval_snapshots';

    protected $fillable = [
        'module_type',
        'module_id',
        'flow_id',
        'flow_name',
        'steps_snapshot',
        'status',
        'current_step_order',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'steps_snapshot' => 'array',
    ];

    public function activity()
    {
        return $this->hasOne(ApprovalActivities::class, 'snapshot_id', 'id');
    }
}
