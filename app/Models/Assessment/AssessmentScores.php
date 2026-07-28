<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAssessmentScores
 * @property string $id
 * @property string $assessment_user_id
 * @property string $template_item_id
 * @property numeric|null $score
 * @property \Illuminate\Support\Carbon|null $execution_date
 * @property string|null $notes
 * @property numeric|null $weighted_score
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Assessment\AssessmentUsers|null $assessmentUser
 * @property-read \App\Models\Assessment\AssessmentTemplateItems $templateItem
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereAssessmentUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereExecutionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereTemplateItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores whereWeightedScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentScores withoutTrashed()
 * @mixin \Eloquent
 */
class AssessmentScores extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'assessment_scores';

    protected $fillable = [
        'assessment_user_id',
        'template_item_id',
        'score',
        'weighted_score',
        'comment',
        'execution_date',
        'notes',
    ];

    protected $casts = [
        'execution_date' => 'date',
    ];

    public function assessmentUser()
    {
        return $this->belongsTo(AssessmentUsers::class, 'assessment_user_id');
    }

    public function templateItem()
    {
        return $this->belongsTo(AssessmentTemplateItems::class, 'template_item_id');
    }   
}
