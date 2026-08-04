<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class CandidateAdditionalInfos extends Model
{
    use HasUlids;

    protected $table = 'candt_additional_infos';

    protected $fillable = [
        'candidate_id',
        'allow_reference_check',
        'has_disease',
        'disease_notes',
        'hobbies',
        'languages',
        'last_facilities',
        'expected_salary_facilities',
        'available_start_date',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
