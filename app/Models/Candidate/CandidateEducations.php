<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class CandidateEducations extends Model
{
    use HasUlids;

    protected $table = 'candt_educations';

    protected $fillable = [
        'candidate_id',
        'level',
        'school_name',
        'major',
        'gpa',
        'start_year',
        'end_year',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
