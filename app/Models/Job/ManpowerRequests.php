<?php

namespace App\Models\Job;

use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperManpowerRequests
 * @property string $id
 * @property string $company_id
 * @property string|null $requested_by
 * @property string $job_position_id
 * @property string|null $type
 * @property int $quantity
 * @property string|null $notes
 * @property string $status
 * @property string|null $file_path
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalHistories> $approvalHistories
 * @property-read int|null $approval_histories_count
 * @property-read ApprovalSnapshots|null $approvalSnapshot
 * @property-read \App\Models\Master\Companies $company
 * @property-read JobPositions|null $jobPosition
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereJobPositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManpowerRequests withoutTrashed()
 * @mixin \Eloquent
 */
class ManpowerRequests extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'manpower_requests';

    protected $fillable = [
        'company_id',
        'requested_by',
        'job_position_id',
        'type',
        'quantity',
        'notes',
        'status',
        'file_path',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Master\Companies::class, 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function jobPosition()
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id');
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id');
    }
}
