<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateTrainings
 */
class CandidateTrainings extends Model
{
    use HasUlids;

    protected $table = 'candt_trainings';

    protected $fillable = [
        'candidate_id',
        'type',
        'institution',
        'material',
        'duration',
        'location',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
