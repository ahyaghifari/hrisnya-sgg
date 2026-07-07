<?php

namespace App\Models\ApprovalManagement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperApprovalHistories
 */
class ApprovalHistories extends Model
{
    use HasUlids;

    protected $table = 'approval_histories';

    protected $fillable = [
        'snapshot_id',
        'activity_id',
        'action',
        'actor_id',
        'actor_name',
        'note',
        'metadata',
        'requestable_type',
        'requestable_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function requestable()
    {
        return $this->morphTo();
    }
}
