<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateWorkExperience
 */
class CandidateWorkExperience extends Model
{
    use HasUlids;

    protected $table = 'candt_work_experiences';

    protected $fillable = [
        'candidate_id',
        'company_name',
        'business_type',
        'company_address',
        'position',
        'start_date',
        'end_date',
        'salary',
        'resign_reason',
        'is_current',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
