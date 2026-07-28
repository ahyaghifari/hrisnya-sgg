<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @mixin IdeHelperCandidateTrainings
 * @property string $id
 * @property string $candidate_id
 * @property string|null $type
 * @property string|null $institution
 * @property string|null $material
 * @property string|null $duration
 * @property string|null $location
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereInstitution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereMaterial($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateTrainings whereUpdatedAt($value)
 * @mixin \Eloquent
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
