<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class CandidateFamilyMembers extends Model
{
    use HasUlids;

    protected $table = 'candt_family_members';

    protected $fillable = [
        'candidate_id',
        'relation',
        'gender',
        'name',
        'birth_date',
        'education',
        'job',
        'phone',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
