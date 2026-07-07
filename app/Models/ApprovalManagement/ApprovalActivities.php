<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApprovalActivities
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
