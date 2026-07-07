<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateOrganizations
 */
class CandidateOrganizations extends Model
{
    use HasUlids;

    protected $table = 'candt_organizations';

    protected $fillable = [
        'candidate_id',
        'organization_name',
        'field',
        'position',
        'duration',
        'location',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
