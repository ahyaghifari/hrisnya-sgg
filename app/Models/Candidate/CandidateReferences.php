<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateReferences
 */
class CandidateReferences extends Model
{
    use HasUlids;

    protected $table = 'candt_references';

    protected $fillable = [
        'candidate_id',
        'name',
        'address',
        'job',
        'phone',
        'relation',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
