<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
