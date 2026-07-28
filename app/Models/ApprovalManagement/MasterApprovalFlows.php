<?php

namespace App\Models\ApprovalManagement;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperMasterApprovalFlows
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string $module
 * @property string $flow_type
 * @property string|null $description
 * @property bool $is_active
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property int|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApprovalManagement\ApprovalFlowAssignments> $assignments
 * @property-read int|null $assignments_count
 * @property-read Companies $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApprovalManagement\MasterApprovalFlowSteps> $steps
 * @property-read int|null $steps_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereFlowType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereModule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterApprovalFlows withoutTrashed()
 * @mixin \Eloquent
 */
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
