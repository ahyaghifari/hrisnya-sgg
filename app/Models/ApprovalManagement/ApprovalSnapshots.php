<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApprovalSnapshots
 * @property string $id
 * @property string $module_type
 * @property string $module_id
 * @property string $flow_id
 * @property string $flow_name
 * @property array<array-key, mixed> $steps_snapshot
 * @property string $status
 * @property int|null $current_step_order
 * @property string|null $company_id
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ApprovalManagement\ApprovalActivities|null $activity
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereCurrentStepOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereFlowId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereFlowName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereModuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereModuleType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereStepsSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApprovalSnapshots whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
