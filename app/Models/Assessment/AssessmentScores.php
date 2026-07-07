<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAssessmentScores
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
