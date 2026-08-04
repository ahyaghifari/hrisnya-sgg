<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTransitionDetail extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_transition_id',
        'target_achievement',
        'target_comment',
        'work_quality',
        'work_quality_comment',
        'productivity_level',
        'productivity_comment',
        'responsibility_adherence',
        'responsibility_comment',
        'work_ethic',
        'work_ethic_comment',
        'teamwork',
        'teamwork_comment',
        'external_factors',
        'employee_request',
        'other_comments',
    ];

    public function userTransition(): BelongsTo
    {
        return $this->belongsTo(UserTransition::class);
    }
}
