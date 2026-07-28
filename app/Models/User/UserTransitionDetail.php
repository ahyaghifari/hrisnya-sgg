<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUserTransitionDetail
 * @property string $id
 * @property string $user_transition_id
 * @property string|null $target_achievement
 * @property string|null $target_comment
 * @property string|null $work_quality
 * @property string|null $work_quality_comment
 * @property string|null $productivity_level
 * @property string|null $productivity_comment
 * @property string|null $responsibility_adherence
 * @property string|null $responsibility_comment
 * @property string|null $work_ethic
 * @property string|null $work_ethic_comment
 * @property string|null $teamwork
 * @property string|null $teamwork_comment
 * @property string|null $external_factors
 * @property string|null $employee_request
 * @property string|null $other_comments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User\UserTransition $userTransition
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereEmployeeRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereExternalFactors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereOtherComments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereProductivityComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereProductivityLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereResponsibilityAdherence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereResponsibilityComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereTargetAchievement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereTargetComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereTeamwork($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereTeamworkComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereUserTransitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereWorkEthic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereWorkEthicComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereWorkQuality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransitionDetail whereWorkQualityComment($value)
 * @mixin \Eloquent
 */
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
