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
