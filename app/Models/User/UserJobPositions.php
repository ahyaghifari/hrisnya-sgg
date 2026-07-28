<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserJobPositions
 * @property string $id
 * @property string $user_id
 * @property string $job_position_id
 * @property string|null $assigned_by
 * @property string $start_date
 * @property string|null $end_date
 * @property string $status
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\JobPositions|null $jobPosition
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereAssignedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereJobPositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserJobPositions whereUserId($value)
 * @mixin \Eloquent
 */
class UserJobPositions extends Model
{
    use HasUlids;

    protected $table = 'user_job_positions';

    protected $fillable = [
        'user_id',
        'job_position_id',
        'assigned_by',
        'start_date',
        'end_date',
        'status',
    ];

    public function jobPosition()
    {
        return $this->belongsTo(\App\Models\Master\JobPositions::class, 'job_position_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }


}
