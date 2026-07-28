<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateReferences
 * @property string $id
 * @property string $candidate_id
 * @property string|null $name
 * @property string|null $address
 * @property string|null $job
 * @property string|null $phone
 * @property string|null $relation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereJob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereRelation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateReferences whereUpdatedAt($value)
 * @mixin \Eloquent
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
