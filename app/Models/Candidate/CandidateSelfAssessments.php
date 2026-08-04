<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

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
