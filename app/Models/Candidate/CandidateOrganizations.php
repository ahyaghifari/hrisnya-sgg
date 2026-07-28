<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateOrganizations
 * @property string $id
 * @property string $candidate_id
 * @property string|null $organization_name
 * @property string|null $field
 * @property string|null $position
 * @property string|null $duration
 * @property string|null $location
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereField($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereOrganizationName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateOrganizations whereUpdatedAt($value)
 * @mixin \Eloquent
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
