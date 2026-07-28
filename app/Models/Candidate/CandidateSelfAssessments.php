<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateSelfAssessments
 * @property string $id
 * @property string $candidate_id
 * @property string|null $strengths
 * @property string|null $weaknesses
 * @property string|null $satisfaction_level
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereSatisfactionLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereStrengths($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSelfAssessments whereWeaknesses($value)
 * @mixin \Eloquent
 */
class CandidateSelfAssessments extends Model
{
    use HasUlids;

    protected $table = 'candt_self_assessments';

    protected $fillable = [
        'candidate_id',
        'strengths',
        'weaknesses',
        'satisfaction_level',
        'reason',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
